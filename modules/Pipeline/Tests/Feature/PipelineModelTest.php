<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PipelineModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_casts_run_columns(): void
    {
        $run = PipelineRun::factory()->create([
            'stages' => ['ingest', 'read'],
            'status' => RunStatus::Running,
            'trigger_source' => TriggerSource::ManualUpload,
            'error_summary' => ['step_key' => 'ocr', 'message' => 'boom'],
        ]);

        $run->refresh();

        $this->assertSame(['ingest', 'read'], $run->stages);
        $this->assertSame(RunStatus::Running, $run->status);
        $this->assertSame(TriggerSource::ManualUpload, $run->trigger_source);
        $this->assertSame('ocr', $run->error_summary['step_key']);
        $this->assertNotEmpty($run->hash_id);
    }

    #[Test]
    public function it_generates_a_hash_id(): void
    {
        $run = PipelineRun::factory()->create();

        $this->assertSame(10, mb_strlen($run->hash_id));
    }

    #[Test]
    public function it_casts_step_columns(): void
    {
        $step = PipelineRunStep::factory()->create([
            'status' => StepStatus::Pending,
            'depends_on' => ['store_file'],
            'allow_failure' => true,
            'is_gate' => false,
            'config' => ['threshold' => 0.5],
            'findings' => [['code' => 'low_ocr_confidence', 'severity' => 'warning']],
            'confidence' => 0.875,
        ]);

        $step->refresh();

        $this->assertSame(StepStatus::Pending, $step->status);
        $this->assertSame(['store_file'], $step->depends_on);
        $this->assertTrue($step->allow_failure);
        $this->assertSame(0.5, $step->config['threshold']);
        $this->assertSame('low_ocr_confidence', $step->findings[0]['code']);
        $this->assertSame(0.875, $step->confidence);
    }

    #[Test]
    public function a_run_has_steps_and_artifacts(): void
    {
        $run = PipelineRun::factory()->create();
        $step = PipelineRunStep::factory()->for($run, 'run')->create();
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'ocr_text',
            'kind' => ArtifactKind::Text,
            'payload' => ['text' => 'ALDI 1234'],
        ]);

        $this->assertCount(1, $run->steps);
        $this->assertCount(1, $run->artifacts);
        $this->assertSame(ArtifactKind::Text, $run->artifacts->first()?->kind);
        $this->assertSame('ALDI 1234', $run->artifacts->first()?->payload['text']);
    }

    #[Test]
    public function current_steps_returns_only_the_highest_attempt_per_key(): void
    {
        $run = PipelineRun::factory()->create();
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'ocr', 'attempt' => 1, 'status' => StepStatus::Failed,
        ]);
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'ocr', 'attempt' => 2, 'status' => StepStatus::Pending,
        ]);
        PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => 'classify', 'attempt' => 1, 'status' => StepStatus::Pending,
        ]);

        $current = $run->currentSteps();

        $this->assertCount(2, $current);
        $this->assertSame(2, $current->firstWhere('step_key', 'ocr')?->attempt);
    }

    #[Test]
    public function the_run_owns_a_users_pipeline_runs_only(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();

        $this->assertSame($user->id, $run->owner?->id);
    }
}
