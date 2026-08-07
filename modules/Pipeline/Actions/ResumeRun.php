<?php

declare(strict_types=1);

namespace Modules\Pipeline\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Events\RunStatusChanged;
use Modules\Pipeline\Exceptions\RunNotAwaitingManualException;
use Modules\Pipeline\Exceptions\StepNotInRunException;
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
     * @param  list<string>  $reopen  steps to run again, because the decision
     *                                changed an input they already consumed
     */
    public function approve(PipelineRun $run, array $artifacts = [], array $reopen = []): PipelineRun
    {
        $gate = $this->gateStep($run);
        $from = $run->status;
        $current = $run->currentSteps()->keyBy('step_key');

        DB::transaction(function () use ($run, $gate, $artifacts, $reopen, $current): void {
            foreach ($artifacts as $pending) {
                $this->artifacts->write($gate, $pending);
            }

            $gate->update([
                'status' => StepStatus::Succeeded,
                'finished_at' => now(),
            ]);

            foreach ($reopen as $key) {
                /** @var PipelineRunStep|null $step */
                $step = $current->get($key);

                if ($step === null) {
                    throw StepNotInRunException::for($run, $key);
                }

                $run->steps()->create([
                    'step_key' => $step->step_key,
                    'stage' => $step->stage,
                    'stage_position' => $step->stage_position,
                    'position' => $step->position,
                    'attempt' => $step->attempt + 1,
                    'max_attempts' => $step->max_attempts,
                    'status' => StepStatus::Pending,
                    'depends_on' => $step->depends_on,
                    'allow_failure' => $step->allow_failure,
                    'is_gate' => $step->is_gate,
                    'config' => $step->config,
                    'added_by_step_id' => $step->added_by_step_id,
                ]);
            }

            $run->update(['status' => RunStatus::Running, 'expires_at' => null]);
        });

        RunStatusChanged::dispatch($run, $from, RunStatus::Running);
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
