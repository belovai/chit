<?php

declare(strict_types=1);

namespace Modules\Pipeline\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\Pipeline\Exceptions\InvalidDefinitionException;
use Modules\Pipeline\Jobs\AdvanceRun;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Registries\PipelineRegistry;
use Modules\Pipeline\ValueObjects\StepDefinition;

final class StartRun
{
    public function __construct(private readonly PipelineRegistry $pipelines) {}

    /**
     * @param  array<string, array<string, mixed>>  $config  per-step config overrides, keyed by step key
     */
    public function handle(
        string $definitionKey,
        int $ownerId,
        ?Model $subject = null,
        TriggerSource $trigger = TriggerSource::ManualUpload,
        array $config = [],
        ?int $retriedFromRunId = null,
        ?int $aiCredentialId = null,
    ): PipelineRun {
        $definition = $this->pipelines->get($definitionKey);
        $stages = $definition->stages();
        $steps = $definition->steps();

        $this->assertValid($steps, $stages);

        $run = DB::transaction(function () use (
            $definition, $stages, $steps, $ownerId, $subject, $trigger, $config, $retriedFromRunId, $aiCredentialId,
        ): PipelineRun {
            $run = PipelineRun::query()->create([
                'owner_id' => $ownerId,
                'ai_credential_id' => $aiCredentialId,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'definition_key' => $definition->key(),
                'definition_version' => $definition->version(),
                'stages' => $stages,
                'status' => RunStatus::Queued,
                'trigger_source' => $trigger,
                'retried_from_run_id' => $retriedFromRunId,
                'queued_at' => now(),
                'cost_usd_micros' => 0,
            ]);

            $positionInStage = [];

            foreach ($steps as $step) {
                $stagePosition = (int) array_search($step->stage(), $stages, true);
                $positionInStage[$step->stage()] = ($positionInStage[$step->stage()] ?? -1) + 1;

                $run->steps()->create([
                    'step_key' => $step->key(),
                    'stage' => $step->stage(),
                    'stage_position' => $stagePosition,
                    'position' => $positionInStage[$step->stage()],
                    'attempt' => 1,
                    'max_attempts' => $step->attempts(),
                    'status' => StepStatus::Pending,
                    'depends_on' => $step->dependencies(),
                    'allow_failure' => $step->isAllowFailure(),
                    'is_gate' => $step->isGate(),
                    'config' => [...$step->config(), ...($config[$step->key()] ?? [])],
                ]);
            }

            return $run->fresh() ?? $run;
        });

        AdvanceRun::dispatch($run->id);

        return $run;
    }

    /**
     * @param  list<StepDefinition>  $steps
     * @param  list<string>  $stages
     */
    private function assertValid(array $steps, array $stages): void
    {
        $keys = [];

        foreach ($steps as $step) {
            if (in_array($step->key(), $keys, true)) {
                throw InvalidDefinitionException::duplicateStepKey($step->key());
            }

            $keys[] = $step->key();

            if (!in_array($step->stage(), $stages, true)) {
                throw InvalidDefinitionException::unknownStage($step->key(), $step->stage());
            }
        }

        foreach ($steps as $step) {
            foreach ($step->dependencies() as $dependency) {
                if (!in_array($dependency, $keys, true)) {
                    throw InvalidDefinitionException::unknownDependency($step->key(), $dependency);
                }
            }
        }
    }
}
