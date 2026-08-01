<?php

declare(strict_types=1);

namespace Modules\Pipeline\Services;

use Illuminate\Support\Facades\DB;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Exceptions\InvalidExpansionException;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\ValueObjects\StepDefinition;

/**
 * Inserts steps into a live run. Validates everything before writing anything,
 * so a bad expansion never half-applies — it fails the step that requested it.
 */
final class RunExpander
{
    /**
     * @param  list<StepDefinition>  $definitions
     */
    public function expand(PipelineRunStep $addedBy, array $definitions): void
    {
        /** @var PipelineRun $run */
        $run = $addedBy->run;
        /** @var list<string> $stages */
        $stages = $run->stages;
        $existing = $run->currentSteps();

        /** @var array<string, list<string>> $graph */
        $graph = [];
        foreach ($existing as $step) {
            /** @var list<string> $dependsOn */
            $dependsOn = array_values($step->depends_on ?? []);
            $graph[$step->step_key] = $dependsOn;
        }

        foreach ($definitions as $definition) {
            if (!in_array($definition->stage(), $stages, true)) {
                throw InvalidExpansionException::unknownStage($definition->key(), $definition->stage());
            }

            if (isset($graph[$definition->key()])) {
                throw InvalidExpansionException::duplicateStepKey($definition->key());
            }

            $graph[$definition->key()] = $definition->dependencies();
        }

        foreach ($definitions as $definition) {
            foreach ($definition->dependencies() as $dependency) {
                if (!isset($graph[$dependency])) {
                    throw InvalidExpansionException::unknownDependency($definition->key(), $dependency);
                }
            }
        }

        $this->assertAcyclic($graph);

        // Next free position within each target stage.
        $nextPosition = [];
        foreach ($existing as $step) {
            $nextPosition[$step->stage] = max($nextPosition[$step->stage] ?? -1, $step->position);
        }

        DB::transaction(function () use ($definitions, $stages, $run, $addedBy, &$nextPosition): void {
            foreach ($definitions as $definition) {
                $stage = $definition->stage();
                $nextPosition[$stage] = ($nextPosition[$stage] ?? -1) + 1;

                $run->steps()->create([
                    'step_key' => $definition->key(),
                    'stage' => $stage,
                    'stage_position' => (int) array_search($stage, $stages, true),
                    'position' => $nextPosition[$stage],
                    'attempt' => 1,
                    'max_attempts' => $definition->attempts(),
                    'status' => StepStatus::Pending,
                    'depends_on' => $definition->dependencies(),
                    'allow_failure' => $definition->isAllowFailure(),
                    'is_gate' => $definition->isGate(),
                    'config' => $definition->config(),
                    'added_by_step_id' => $addedBy->id,
                ]);
            }
        });
    }

    /**
     * Iterative DFS with a three-colour marking, so a deep graph cannot blow the stack.
     *
     * @param  array<string, list<string>>  $graph
     */
    private function assertAcyclic(array $graph): void
    {
        /** @var array<string, int> $colour 0 = unvisited, 1 = on stack, 2 = done */
        $colour = array_fill_keys(array_keys($graph), 0);

        foreach (array_keys($graph) as $start) {
            if ($colour[$start] !== 0) {
                continue;
            }

            /** @var list<string> $stack */
            $stack = [$start];

            while ($stack !== []) {
                $node = $stack[count($stack) - 1];

                if ($colour[$node] === 0) {
                    $colour[$node] = 1;
                }

                $descended = false;

                foreach ($graph[$node] as $dependency) {
                    if ($colour[$dependency] === 1) {
                        throw InvalidExpansionException::cycle([...$stack, $dependency]);
                    }

                    if ($colour[$dependency] === 0) {
                        $stack[] = $dependency;
                        $descended = true;
                        break;
                    }
                }

                if (!$descended) {
                    $colour[$node] = 2;
                    array_pop($stack);
                }
            }
        }
    }
}
