<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Support;

use Illuminate\Support\Facades\Storage;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\Services\ArtifactWriter;
use Modules\Pipeline\Services\StepContextFactory;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Receipt\Models\Receipt;
use Modules\User\Models\User;

/**
 * Drives one step at a time against a hand-built run, so a step's behaviour can
 * be asserted without running the whole pipeline.
 */
trait MakesReceiptRuns
{
    /**
     * @param  array<string, mixed>  $receiptOverrides
     * @return array{0: Receipt, 1: PipelineRun}
     */
    protected function receiptRun(array $receiptOverrides = []): array
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user, 'owner')->create($receiptOverrides);
        $run = PipelineRun::factory()->for($user, 'owner')->create([
            'subject_type' => Receipt::class,
            'subject_id' => $receipt->id,
            'stages' => ['ingest', 'prepare', 'read', 'classify', 'extract', 'resolve', 'validate', 'review', 'commit'],
        ]);
        $receipt->update(['current_run_id' => $run->id]);

        return [$receipt->refresh(), $run];
    }

    protected function stepRow(PipelineRun $run, string $stepKey, string $stage = 'ingest'): PipelineRunStep
    {
        return PipelineRunStep::factory()->for($run, 'run')->create([
            'step_key' => $stepKey,
            'stage' => $stage,
            'status' => StepStatus::Running,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function seedArtifact(PipelineRunStep $step, string $key, array $payload): void
    {
        app(ArtifactWriter::class)->write(
            $step,
            new PendingArtifact($key, ArtifactKind::Json, $payload),
        );
    }

    protected function seedTextArtifact(PipelineRunStep $step, string $key, string $text): void
    {
        app(ArtifactWriter::class)->write(
            $step,
            new PendingArtifact($key, ArtifactKind::Text, ['text' => $text]),
        );
    }

    protected function contextFor(PipelineRunStep $step): StepContext
    {
        return app(StepContextFactory::class)->for($step->refresh());
    }

    protected function putFile(string $disk, string $path, string $contents): void
    {
        Storage::disk($disk)->put($path, $contents);
    }
}
