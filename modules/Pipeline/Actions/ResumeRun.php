<?php

declare(strict_types=1);

namespace Modules\Pipeline\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Exceptions\RunNotAwaitingManualException;
use Modules\Pipeline\Jobs\AdvanceRun;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\Services\ArtifactWriter;
use Modules\Pipeline\Services\RunFinalizer;
use Modules\Pipeline\ValueObjects\PendingArtifact;

/** Releases a run parked on a gate. The only way out of `awaiting_manual`. */
final class ResumeRun
{
    public function __construct(
        private readonly ArtifactWriter $artifacts,
        private readonly RunFinalizer $finalizer,
    ) {}

    /**
     * @param  list<PendingArtifact>  $artifacts
     */
    public function approve(PipelineRun $run, array $artifacts = []): PipelineRun
    {
        $gate = $this->gateStep($run);

        DB::transaction(function () use ($run, $gate, $artifacts): void {
            foreach ($artifacts as $pending) {
                $this->artifacts->write($gate, $pending);
            }

            $gate->update([
                'status' => StepStatus::Succeeded,
                'finished_at' => now(),
            ]);

            $run->update(['status' => RunStatus::Running, 'expires_at' => null]);
        });

        AdvanceRun::dispatch($run->id);

        return $run->refresh();
    }

    /**
     * @param  list<PendingArtifact>  $artifacts
     */
    public function reject(PipelineRun $run, array $artifacts = []): PipelineRun
    {
        $gate = $this->gateStep($run);

        DB::transaction(function () use ($run, $gate, $artifacts): void {
            foreach ($artifacts as $pending) {
                $this->artifacts->write($gate, $pending);
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
        });

        return $run->refresh();
    }

    private function gateStep(PipelineRun $run): PipelineRunStep
    {
        if ($run->status !== RunStatus::AwaitingManual) {
            throw RunNotAwaitingManualException::for($run);
        }

        return $run->currentSteps()
            ->firstWhere('status', StepStatus::AwaitingManual)
            ?? throw RunNotAwaitingManualException::for($run);
    }
}
