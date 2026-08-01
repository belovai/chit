<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_expires_a_run_parked_past_its_deadline(): void
    {
        $run = PipelineRun::factory()->create([
            'status' => RunStatus::AwaitingManual,
            'expires_at' => now()->subDay(),
        ]);
        $step = PipelineRunStep::factory()->for($run, 'run')->create([
            'status' => StepStatus::AwaitingManual,
            'is_gate' => true,
        ]);

        $this->artisan('pipeline:expire-stale-runs')->assertSuccessful();

        $this->assertSame(RunStatus::Expired, $run->refresh()->status);
        $this->assertSame(StepStatus::Expired, $step->refresh()->status);
        $this->assertNull($run->expires_at);
    }

    #[Test]
    public function it_leaves_a_run_still_within_its_deadline_alone(): void
    {
        $run = PipelineRun::factory()->create([
            'status' => RunStatus::AwaitingManual,
            'expires_at' => now()->addDays(5),
        ]);

        $this->artisan('pipeline:expire-stale-runs')->assertSuccessful();

        $this->assertSame(RunStatus::AwaitingManual, $run->refresh()->status);
    }

    #[Test]
    public function it_prunes_an_expired_binary_artifact_but_keeps_the_row(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('runs/1/image.png', 'binary-bytes');

        $run = PipelineRun::factory()->create(['status' => RunStatus::Succeeded]);
        $step = PipelineRunStep::factory()->for($run, 'run')->create(['status' => StepStatus::Succeeded]);
        $artifact = PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'normalized_image',
            'kind' => ArtifactKind::Binary,
            'payload' => null,
            'disk' => 'local',
            'path' => 'runs/1/image.png',
            'size_bytes' => 12,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('pipeline:prune-artifacts')->assertSuccessful();

        $artifact->refresh();
        $this->assertNull($artifact->path);
        $this->assertNull($artifact->size_bytes);
        $this->assertSame('normalized_image', $artifact->key);
        Storage::disk('local')->assertMissing('runs/1/image.png');
    }

    #[Test]
    public function it_never_prunes_structured_artifacts(): void
    {
        $run = PipelineRun::factory()->create(['status' => RunStatus::Succeeded]);
        $step = PipelineRunStep::factory()->for($run, 'run')->create(['status' => StepStatus::Succeeded]);
        $artifact = PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'ocr_text',
            'kind' => ArtifactKind::Text,
            'payload' => ['text' => 'ALDI 1234'],
            'expires_at' => now()->subYear(),
        ]);

        $this->artisan('pipeline:prune-artifacts')->assertSuccessful();

        $this->assertSame(['text' => 'ALDI 1234'], $artifact->refresh()->payload);
    }
}
