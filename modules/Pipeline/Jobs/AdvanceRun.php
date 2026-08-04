<?php

declare(strict_types=1);

namespace Modules\Pipeline\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Events\RunStatusChanged;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\Registries\StepRegistry;
use Modules\Pipeline\Services\ReadyStepResolver;
use Modules\Pipeline\Services\RunFinalizer;
use Modules\Pipeline\ValueObjects\StepSnapshot;
use RuntimeException;

/**
 * Re-evaluates the whole run after every step transition and queues whatever
 * became ready. This is deliberately not a Bus::chain — a chain is fixed, and
 * the engine must support mid-run expansion, pausing on a gate, and restarting
 * from the middle. Re-evaluation gives all three from one mechanism.
 */
final class AdvanceRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_ITERATIONS = 500;

    public function __construct(public readonly int $runId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->runId))->releaseAfter(5)->expireAfter(120)];
    }

    public function handle(
        ReadyStepResolver $resolver,
        RunFinalizer $finalizer,
        StepRegistry $registry,
    ): void {
        $run = PipelineRun::query()->find($this->runId);

        if ($run === null || $run->status->isTerminal()) {
            return;
        }

        // Re-evaluate in a loop instead of relying solely on the follow-up
        // dispatch from ExecuteStep. Three reasons:
        //  1. On the `sync` driver (tests) ExecuteStep runs INSIDE this call
        //     stack, so the AdvanceRun it dispatches hits our own
        //     WithoutOverlapping lock and is released — i.e. dropped. Without
        //     this loop a synchronous run would stall after its first step.
        //  2. On a real queue the second iteration finds the steps still
        //     `queued` and exits at once — one cheap extra pass.
        //  3. It makes the engine self-healing if a follow-up job is ever lost.
        $iterations = 0;

        while (true) {
            if (++$iterations > self::MAX_ITERATIONS) {
                throw new RuntimeException("AdvanceRun for run [{$this->runId}] did not settle.");
            }

            $run->refresh();

            if ($run->status->isTerminal() || $run->status === RunStatus::AwaitingManual) {
                return;
            }

            $current = $run->currentSteps();
            $plan = $resolver->resolve($this->snapshots($current));

            if ($plan->skipStepIds !== []) {
                PipelineRunStep::query()
                    ->whereIn('id', $plan->skipStepIds)
                    ->update(['status' => StepStatus::Skipped, 'finished_at' => now()]);

                continue;
            }

            if ($plan->readyStepIds !== []) {
                if ($run->status === RunStatus::Queued) {
                    $this->transition($run, RunStatus::Running, ['started_at' => now()]);
                }

                PipelineRunStep::query()
                    ->whereIn('id', $plan->readyStepIds)
                    ->update(['status' => StepStatus::Queued]);

                foreach ($current->whereIn('id', $plan->readyStepIds) as $step) {
                    ExecuteStep::dispatch($step->id)
                        ->onQueue($registry->classFor($step->step_key)::queue());
                }

                continue;
            }

            if ($plan->isAwaitingManual) {
                return;
            }

            if ($plan->isComplete && $plan->finalStatus !== null) {
                $finalizer->finalize($run, $plan->finalStatus);
            }

            return;
        }
    }

    /**
     * @param  Collection<int, PipelineRunStep>  $steps
     * @return list<StepSnapshot>
     */
    private function snapshots($steps): array
    {
        return array_values($steps->map(fn (PipelineRunStep $step): StepSnapshot => new StepSnapshot(
            id: $step->id,
            stepKey: $step->step_key,
            stagePosition: $step->stage_position,
            status: $step->status,
            dependsOn: array_values($step->depends_on ?? []),
            allowFailure: $step->allow_failure,
        ))->all());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(PipelineRun $run, RunStatus $to, array $extra = []): void
    {
        $from = $run->status;
        $run->update([...$extra, 'status' => $to]);
        RunStatusChanged::dispatch($run, $from, $to);
    }
}
