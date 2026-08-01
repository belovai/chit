<?php

declare(strict_types=1);

namespace Modules\Pipeline\Actions;

use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Services\RunFinalizer;

final class CancelRun
{
    public function __construct(private readonly RunFinalizer $finalizer) {}

    /** Idempotent: a run that already finished is returned untouched. */
    public function handle(PipelineRun $run): PipelineRun
    {
        if ($run->status->isTerminal()) {
            return $run;
        }

        $run->steps()
            ->whereIn('status', [
                StepStatus::Pending->value,
                StepStatus::Queued->value,
                StepStatus::Running->value,
                StepStatus::AwaitingManual->value,
            ])
            ->update(['status' => StepStatus::Canceled, 'finished_at' => now()]);

        $run->update(['expires_at' => null]);
        $this->finalizer->finalize($run->refresh(), RunStatus::Canceled);

        return $run->refresh();
    }
}
