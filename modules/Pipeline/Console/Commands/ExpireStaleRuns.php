<?php

declare(strict_types=1);

namespace Modules\Pipeline\Console\Commands;

use Illuminate\Console\Command;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Services\RunFinalizer;

/**
 * Without this, a run nobody ever reviews sits in `awaiting_manual` forever and
 * the active list slowly fills with abandoned work. Expired runs stay retryable.
 */
final class ExpireStaleRuns extends Command
{
    protected $signature = 'pipeline:expire-stale-runs';

    protected $description = 'Close pipeline runs that have waited too long for a manual decision';

    public function handle(RunFinalizer $finalizer): int
    {
        $runs = PipelineRun::query()
            ->where('status', RunStatus::AwaitingManual->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($runs as $run) {
            $run->steps()
                ->where('status', StepStatus::AwaitingManual->value)
                ->update(['status' => StepStatus::Expired, 'finished_at' => now()]);

            $run->steps()
                ->whereIn('status', [StepStatus::Pending->value, StepStatus::Queued->value])
                ->update(['status' => StepStatus::Canceled, 'finished_at' => now()]);

            $run->update(['expires_at' => null]);
            $finalizer->finalize($run->refresh(), RunStatus::Expired);
        }

        $this->info("Expired {$runs->count()} run(s).");

        return self::SUCCESS;
    }
}
