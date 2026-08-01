<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PipelineRunShowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_full_step_detail(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create([
            'stages' => ['read', 'review'],
            'error_summary' => ['step_key' => 'ocr', 'message' => 'engine timed out'],
        ]);
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'ocr',
            'stage' => 'read',
            'stage_position' => 0,
            'status' => StepStatus::Failed,
            'attempt' => 2,
            'max_attempts' => 3,
            'depends_on' => ['store_file'],
            'confidence' => 0.412,
            'findings' => [['code' => 'low_ocr_confidence', 'severity' => 'warning', 'message' => null, 'context' => []]],
            'input_tokens' => 100,
            'output_tokens' => 20,
            'cost_usd_micros' => 900,
            'error' => ['class' => 'RuntimeException', 'message' => 'engine timed out', 'retryable' => true],
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}");

        $response->assertOk();
        $response->assertJsonPath('data.stages', ['read', 'review']);
        $response->assertJsonPath('data.error_summary.step_key', 'ocr');
        $response->assertJsonPath('data.steps.0.attempt', 2);
        $response->assertJsonPath('data.steps.0.max_attempts', 3);
        $response->assertJsonPath('data.steps.0.depends_on', ['store_file']);
        $response->assertJsonPath('data.steps.0.confidence', 0.412);
        $response->assertJsonPath('data.steps.0.findings.0.code', 'low_ocr_confidence');
        $response->assertJsonPath('data.steps.0.cost_usd_micros', 900);
        $response->assertJsonPath('data.steps.0.error.retryable', true);
    }

    #[Test]
    public function it_lists_artifact_metadata_without_the_payload(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $step = PipelineRunStep::factory()->for($run, 'run')->create(['step_key' => 'ocr']);
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'ocr_text',
            'kind' => ArtifactKind::Text,
            'payload' => ['text' => str_repeat('a', 5000)],
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}");

        $response->assertJsonPath('data.steps.0.artifacts.0.key', 'ocr_text');
        $response->assertJsonPath('data.steps.0.artifacts.0.kind', 'text');
        $response->assertJsonMissingPath('data.steps.0.artifacts.0.payload');
    }

    #[Test]
    public function a_superseded_artifact_is_not_listed(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $step = PipelineRunStep::factory()->for($run, 'run')->create(['step_key' => 'ocr']);
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'ocr_text', 'superseded_at' => now(),
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}");

        $response->assertJsonCount(0, 'data.steps.0.artifacts');
    }

    #[Test]
    public function it_hides_another_users_run(): void
    {
        $run = PipelineRun::factory()->create();
        $intruder = User::factory()->create();
        $token = $intruder->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}")
            ->assertNotFound();
    }
}
