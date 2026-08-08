<?php

declare(strict_types=1);

namespace Modules\Pipeline\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Pipeline\Enums\RetryMode;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\Pipeline\Exceptions\RunNotRetryableException;
use Modules\Pipeline\Exceptions\StepNotInRunException;
use Modules\Pipeline\Jobs\AdvanceRun;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\Services\DownstreamResolver;

final class RetryRun
{
    public function __construct(
        private readonly StartRun $startRun,
        private readonly DownstreamResolver $downstream,
    ) {}

    public function handle(PipelineRun $run, RetryMode $mode, ?string $stepKey = null): PipelineRun
    {
        if (!$run->status->isTerminal() && $run->status !== RunStatus::AwaitingManual) {
            throw RunNotRetryableException::for($run);
        }

        if ($mode === RetryMode::All) {
            return $this->startRun->handle(
                definitionKey: $run->definition_key,
                ownerId: $run->owner_id,
                subject: $run->subject,
                trigger: TriggerSource::Retry,
                config: $this->configFrom($run),
                retriedFromRunId: $run->id,
                aiCredentialId: $run->ai_credential_id,
            );
        }

        $current = $run->currentSteps()->keyBy('step_key');

        if ($stepKey === null || !$current->has($stepKey)) {
            throw StepNotInRunException::for($run, (string) $stepKey);
        }

        /** @var array<string, list<string>> $dependsOnByKey */
        $dependsOnByKey = $current->mapWithKeys(
            fn (PipelineRunStep $step): array => [$step->step_key => $step->depends_on ?? []],
        )->all();

        $targets = $mode === RetryMode::Single
            ? [$stepKey]
            : $this->downstream->closureFor($dependsOnByKey, $stepKey);

        DB::transaction(function () use ($run, $current, $targets): void {
            foreach ($targets as $key) {
                /** @var PipelineRunStep $step */
                $step = $current->get($key);

                PipelineArtifact::query()
                    ->where('run_id', $run->id)
                    ->where('step_id', $step->id)
                    ->whereNull('superseded_at')
                    ->update(['superseded_at' => now()]);

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

            $run->update([
                'status' => RunStatus::Running,
                'started_at' => $run->started_at ?? now(),
                'finished_at' => null,
                'duration_ms' => null,
                'error_summary' => null,
                'expires_at' => null,
            ]);
        });

        AdvanceRun::dispatch($run->id);

        return $run->refresh();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configFrom(PipelineRun $run): array
    {
        return $run->currentSteps()
            ->mapWithKeys(fn (PipelineRunStep $step): array => [$step->step_key => $step->config ?? []])
            ->all();
    }
}
