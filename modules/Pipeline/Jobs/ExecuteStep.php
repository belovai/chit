<?php

declare(strict_types=1);

namespace Modules\Pipeline\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepOutcome;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Exceptions\InvalidExpansionException;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\Registries\StepRegistry;
use Modules\Pipeline\Services\ArtifactWriter;
use Modules\Pipeline\Services\RunExpander;
use Modules\Pipeline\Services\StepContextFactory;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepResult;
use Throwable;

final class ExecuteStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $stepId) {}

    public function handle(
        StepRegistry $registry,
        StepContextFactory $contexts,
        ArtifactWriter $artifacts,
        RunExpander $expander,
    ): void {
        $step = PipelineRunStep::query()->find($this->stepId);

        // Idempotency guard: Horizon may redeliver, and AdvanceRun may race.
        if ($step === null || $step->status->isTerminal() || $step->status === StepStatus::AwaitingManual) {
            return;
        }

        $step->update(['status' => StepStatus::Running, 'started_at' => now()]);

        try {
            $result = $registry->resolve($step->step_key)->handle($contexts->for($step));
        } catch (Throwable $exception) {
            $result = StepResult::failure($exception);
        }

        foreach ($result->artifacts() as $pending) {
            $artifacts->write($step, $pending);
        }

        if ($result->outcome() === StepOutcome::Success && $result->expansions() !== []) {
            try {
                $expander->expand($step, $result->expansions());
            } catch (InvalidExpansionException $exception) {
                // The expanding step's own definition is wrong — fail that step.
                $result = StepResult::failure($exception);
            }
        }

        $this->persistOutcome($step, $result);

        if ($result->outcome() === StepOutcome::Hold) {
            /** @var PipelineRun $run */
            $run = $step->run;
            $run->update([
                'status' => RunStatus::AwaitingManual,
                'expires_at' => now()->addDays((int) config('pipeline.gate.expire_after_days')),
            ]);

            return; // the worker is released; a human resumes the run later
        }

        $delaySeconds = $this->scheduleRetryIfPossible($step, $result);

        AdvanceRun::dispatch($step->run_id)->delay($delaySeconds);
    }

    private function persistOutcome(PipelineRunStep $step, StepResult $result): void
    {
        $status = match ($result->outcome()) {
            StepOutcome::Success => StepStatus::Succeeded,
            StepOutcome::Failure => StepStatus::Failed,
            StepOutcome::Skipped => StepStatus::Skipped,
            StepOutcome::Hold => StepStatus::AwaitingManual,
        };

        $exception = $result->exception();
        $startedAt = $step->started_at;

        $step->update([
            'status' => $status,
            'finished_at' => $result->outcome() === StepOutcome::Hold ? null : now(),
            'duration_ms' => $startedAt !== null ? (int) $startedAt->diffInMilliseconds(now()) : null,
            'confidence' => $result->confidenceValue(),
            'findings' => $result->findings() === []
                ? null
                : array_map(fn (Finding $finding): array => $finding->toArray(), $result->findings()),
            'input_tokens' => $result->inputTokens(),
            'output_tokens' => $result->outputTokens(),
            'cost_usd_micros' => $result->costUsdMicros(),
            'error' => $exception === null ? null : [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'retryable' => $exception instanceof StepException && $exception->isRetryable(),
            ],
        ]);
    }

    /** @return int backoff seconds for the follow-up AdvanceRun, 0 when not retrying */
    private function scheduleRetryIfPossible(PipelineRunStep $step, StepResult $result): int
    {
        if ($result->outcome() !== StepOutcome::Failure) {
            return 0;
        }

        $exception = $result->exception();
        $isRetryable = $exception instanceof StepException && $exception->isRetryable();

        if (!$isRetryable || $step->attempt >= $step->max_attempts) {
            return 0;
        }

        /** @var PipelineRun $run */
        $run = $step->run;

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

        return min(
            (int) config('pipeline.retry.base_backoff_seconds') * (2 ** ($step->attempt - 1)),
            (int) config('pipeline.retry.max_backoff_seconds'),
        );
    }
}
