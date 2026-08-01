<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PipelineRunIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_the_authenticated_users_runs(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        PipelineRun::factory()->for($user, 'owner')->create(['definition_key' => 'mine']);
        PipelineRun::factory()->for($other, 'owner')->create(['definition_key' => 'theirs']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pipeline-runs');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.definition_key', 'mine');
    }

    #[Test]
    public function a_row_carries_the_stage_list_and_compact_steps(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create([
            'stages' => ['ingest', 'read'],
            'status' => RunStatus::Warning,
            'duration_ms' => 1348,
            'cost_usd_micros' => 12400,
        ]);
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'store_file', 'stage' => 'ingest', 'stage_position' => 0,
            'status' => StepStatus::Succeeded,
        ]);
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'ocr', 'stage' => 'read', 'stage_position' => 1,
            'status' => StepStatus::Failed,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pipeline-runs');

        $response->assertOk();
        $response->assertJsonPath('data.0.status', 'warning');
        $response->assertJsonPath('data.0.stages', ['ingest', 'read']);
        $response->assertJsonPath('data.0.duration_ms', 1348);
        $response->assertJsonPath('data.0.cost_usd_micros', 12400);
        $response->assertJsonCount(2, 'data.0.steps');
        $response->assertJsonPath('data.0.steps.0.step_key', 'store_file');
        $response->assertJsonPath('data.0.steps.1.status', 'failed');
    }

    #[Test]
    public function a_row_shows_only_the_latest_attempt_of_each_step(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'ocr', 'attempt' => 1, 'status' => StepStatus::Failed,
        ]);
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'ocr', 'attempt' => 2, 'status' => StepStatus::Succeeded,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pipeline-runs');

        $response->assertJsonCount(1, 'data.0.steps');
        $response->assertJsonPath('data.0.steps.0.status', 'succeeded');
    }

    #[Test]
    public function a_dynamically_added_step_is_flagged(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $parent = PipelineRunStep::factory()->for($run, 'run')->create(['step_key' => 'classify']);
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'extract_receipt',
            'added_by_step_id' => $parent->id,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pipeline-runs');

        $steps = collect($response->json('data.0.steps'))->keyBy('step_key');
        $this->assertFalse($steps['classify']['is_dynamic']);
        $this->assertTrue($steps['extract_receipt']['is_dynamic']);
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        $user = User::factory()->create();
        PipelineRun::factory()->for($user, 'owner')->create(['status' => RunStatus::Failed]);
        PipelineRun::factory()->for($user, 'owner')->create(['status' => RunStatus::Succeeded]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pipeline-runs?status=failed');

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'failed');
    }

    #[Test]
    public function it_filters_by_trigger_source(): void
    {
        $user = User::factory()->create();
        PipelineRun::factory()->for($user, 'owner')->create(['trigger_source' => TriggerSource::Retry]);
        PipelineRun::factory()->for($user, 'owner')->create(['trigger_source' => TriggerSource::ManualUpload]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pipeline-runs?trigger_source=retry');

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.trigger_source', 'retry');
    }

    #[Test]
    public function it_rejects_an_unknown_status_filter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pipeline-runs?status=banana')
            ->assertUnprocessable();
    }

    #[Test]
    public function it_returns_newest_first(): void
    {
        $user = User::factory()->create();
        $older = PipelineRun::factory()->for($user, 'owner')->create(['created_at' => now()->subHour()]);
        $newer = PipelineRun::factory()->for($user, 'owner')->create(['created_at' => now()]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pipeline-runs');

        $response->assertJsonPath('data.0.hash_id', $newer->hash_id);
        $response->assertJsonPath('data.1.hash_id', $older->hash_id);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/pipeline-runs')->assertUnauthorized();
    }
}
