<?php

declare(strict_types=1);

namespace Modules\Pipeline\Services;

use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;

final class RunFinalizer
{
    public function __construct(private readonly ArtifactWriter $artifacts) {}

    public function finalize(PipelineRun $run, RunStatus $status): void
    {
        $current = $run->currentSteps();
        $startedAt = $run->started_at ?? $run->queued_at ?? $run->created_at;

        $run->update([
            'status' => $status,
            'finished_at' => now(),
            'duration_ms' => $startedAt !== null ? (int) $startedAt->diffInMilliseconds(now()) : null,
            'cost_usd_micros' => (int) $current->sum(fn (PipelineRunStep $step): int => (int) $step->cost_usd_micros),
            'expires_at' => null,
            'error_summary' => $this->errorSummary(array_values($current->all())),
        ]);

        $this->artifacts->scheduleBinaryExpiry($run, (int) config('pipeline.artifact_retention_days'));
    }

    /**
     * @param  list<PipelineRunStep>  $steps
     * @return array{step_key: string, message: string}|null
     */
    private function errorSummary(array $steps): ?array
    {
        foreach ($steps as $step) {
            if ($step->status === StepStatus::Failed && !$step->allow_failure) {
                return [
                    'step_key' => $step->step_key,
                    'message' => (string) ($step->error['message'] ?? 'Step failed.'),
                ];
            }
        }

        return null;
    }
}
