<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PipelineRunAttemptsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_every_attempt_oldest_first(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'ocr', 'attempt' => 2, 'status' => StepStatus::Succeeded,
        ]);
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'ocr', 'attempt' => 1, 'status' => StepStatus::Failed,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}/steps/ocr/attempts");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.attempt', 1);
        $response->assertJsonPath('data.0.status', 'failed');
        $response->assertJsonPath('data.1.attempt', 2);
    }

    #[Test]
    public function an_unknown_step_key_returns_an_empty_list(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}/steps/ghost/attempts")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_hides_another_users_run(): void
    {
        $run = PipelineRun::factory()->create();
        $intruder = User::factory()->create();
        $token = $intruder->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}/steps/ocr/attempts")
            ->assertNotFound();
    }
}
