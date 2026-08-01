<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Tests\Support\RegistersFakePipelines;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PipelineRunRetryEndpointTest extends TestCase
{
    use RefreshDatabase, RegistersFakePipelines;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerFakePipelines();
    }

    #[Test]
    public function it_retries_a_single_step(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pipeline-runs/{$run->hash_id}/retry", [
                'mode' => 'single',
                'step_key' => 'fake_failing',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.hash_id', $run->hash_id);
        $this->assertCount(2, $run->steps()->where('step_key', 'fake_failing')->get());
    }

    #[Test]
    public function retrying_the_whole_run_returns_the_new_run(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pipeline-runs/{$run->hash_id}/retry", ['mode' => 'all']);

        $response->assertOk();
        $this->assertNotSame($run->hash_id, $response->json('data.hash_id'));
        $response->assertJsonPath('data.retried_from_hash_id', $run->hash_id);
    }

    #[Test]
    public function single_and_from_require_a_step_key(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pipeline-runs/{$run->hash_id}/retry", ['mode' => 'from'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('step_key');
    }

    #[Test]
    public function a_step_key_not_in_the_run_is_rejected_with_a_coded_message(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pipeline-runs/{$run->hash_id}/retry", [
                'mode' => 'single',
                'step_key' => 'ghost',
            ]);

        $response->assertUnprocessable();
        $this->assertSame('pipeline.step_not_in_run', $response->json('errors.step_key.0'));
    }

    #[Test]
    public function an_unknown_mode_is_rejected(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pipeline-runs/{$run->hash_id}/retry", ['mode' => 'sideways'])
            ->assertUnprocessable();
    }

    #[Test]
    public function retrying_a_run_that_is_still_going_is_a_conflict(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create(['status' => RunStatus::Running]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pipeline-runs/{$run->hash_id}/retry", ['mode' => 'all'])
            ->assertStatus(409);
    }

    #[Test]
    public function it_cancels_a_parked_run(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_expandable', $user->id)->refresh();
        $this->assertSame(RunStatus::AwaitingManual, $run->status);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pipeline-runs/{$run->hash_id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'canceled');
    }

    #[Test]
    public function it_hides_another_users_run(): void
    {
        $run = PipelineRun::factory()->create();
        $intruder = User::factory()->create();
        $token = $intruder->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pipeline-runs/{$run->hash_id}/cancel")
            ->assertNotFound();
    }
}
