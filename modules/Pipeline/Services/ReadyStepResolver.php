<?php

declare(strict_types=1);

namespace Modules\Pipeline\Services;

use LogicException;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\ValueObjects\RunPlan;
use Modules\Pipeline\ValueObjects\StepSnapshot;

/**
 * Pure decision function over the current shape of a run. No DB, no dispatch.
 * AdvanceRun feeds it snapshots and executes whatever it returns.
 */
final class ReadyStepResolver
{
    /**
     * @param  list<StepSnapshot>  $snapshots  one per step_key, highest attempt only
     */
    public function resolve(array $snapshots): RunPlan
    {
        $byKey = [];
        foreach ($snapshots as $snapshot) {
            $byKey[$snapshot->stepKey] = $snapshot;
        }

        $this->assertDependenciesExist($snapshots, $byKey);

        /** @var array<string, StepStatus> $statuses */
        $statuses = [];
        foreach ($snapshots as $snapshot) {
            $statuses[$snapshot->stepKey] = $snapshot->status;
        }

        $skipStepIds = $this->cascadeSkips($snapshots, $byKey, $statuses);
        $readyStepIds = $this->findReady($snapshots, $byKey, $statuses);

        $isAwaitingManual = in_array(StepStatus::AwaitingManual, $statuses, true);

        $hasActive = false;
        foreach ($statuses as $status) {
            if (!$status->isTerminal()) {
                $hasActive = true;
                break;
            }
        }

        $isComplete = $readyStepIds === [] && !$hasActive;

        return new RunPlan(
            readyStepIds: $readyStepIds,
            skipStepIds: $skipStepIds,
            isComplete: $isComplete,
            finalStatus: $isComplete ? $this->finalStatus($snapshots, $statuses) : null,
            isAwaitingManual: $isAwaitingManual,
        );
    }

    /**
     * @param  list<StepSnapshot>  $snapshots
     * @param  array<string, StepSnapshot>  $byKey
     */
    private function assertDependenciesExist(array $snapshots, array $byKey): void
    {
        foreach ($snapshots as $snapshot) {
            foreach ($snapshot->dependsOn as $dependency) {
                if (!isset($byKey[$dependency])) {
                    throw new LogicException(
                        "Step [{$snapshot->stepKey}] depends on unknown step [{$dependency}].",
                    );
                }
            }
        }
    }

    /**
     * Marks pending steps whose dependency failed hard, repeating until stable
     * so a failure propagates all the way down the chain in one pass.
     *
     * @param  list<StepSnapshot>  $snapshots
     * @param  array<string, StepSnapshot>  $byKey
     * @param  array<string, StepStatus>  $statuses
     * @return list<int>
     */
    private function cascadeSkips(array $snapshots, array $byKey, array &$statuses): array
    {
        $skipStepIds = [];

        do {
            $changed = false;

            foreach ($snapshots as $snapshot) {
                if ($statuses[$snapshot->stepKey] !== StepStatus::Pending) {
                    continue;
                }

                foreach ($snapshot->dependsOn as $dependency) {
                    $dependencyStatus = $statuses[$dependency];

                    if ($dependencyStatus->isTerminal() && !$this->isSatisfied($dependencyStatus, $byKey[$dependency])) {
                        $statuses[$snapshot->stepKey] = StepStatus::Skipped;
                        $skipStepIds[] = $snapshot->id;
                        $changed = true;
                        break;
                    }
                }

                if ($statuses[$snapshot->stepKey] === StepStatus::Pending
                    && $this->earlierStageHardFailed($snapshot, $snapshots, $statuses)) {
                    $statuses[$snapshot->stepKey] = StepStatus::Skipped;
                    $skipStepIds[] = $snapshot->id;
                    $changed = true;
                }
            }
        } while ($changed);

        sort($skipStepIds);

        return $skipStepIds;
    }

    /**
     * The failure side of the implicit stage gate: once every step in an
     * earlier stage is terminal, a step whose earlier stage contains an
     * unsatisfied (non allow-failure) outcome is skipped instead of being
     * left waiting on a stage that will never produce a satisfied result.
     *
     * @param  list<StepSnapshot>  $snapshots
     * @param  array<string, StepStatus>  $statuses
     */
    private function earlierStageHardFailed(StepSnapshot $snapshot, array $snapshots, array $statuses): bool
    {
        $hasHardFailure = false;

        foreach ($snapshots as $other) {
            if ($other->stagePosition >= $snapshot->stagePosition) {
                continue;
            }

            $otherStatus = $statuses[$other->stepKey];

            if (!$otherStatus->isTerminal()) {
                return false;
            }

            if (!$this->isSatisfied($otherStatus, $other)) {
                $hasHardFailure = true;
            }
        }

        return $hasHardFailure;
    }

    /**
     * @param  list<StepSnapshot>  $snapshots
     * @param  array<string, StepSnapshot>  $byKey
     * @param  array<string, StepStatus>  $statuses
     * @return list<int>
     */
    private function findReady(array $snapshots, array $byKey, array $statuses): array
    {
        $readyStepIds = [];

        foreach ($snapshots as $snapshot) {
            if ($statuses[$snapshot->stepKey] !== StepStatus::Pending) {
                continue;
            }

            if (!$this->dependenciesSatisfied($snapshot, $byKey, $statuses)) {
                continue;
            }

            if ($this->earlierStageStillActive($snapshot, $snapshots, $statuses)) {
                continue;
            }

            $readyStepIds[] = $snapshot->id;
        }

        sort($readyStepIds);

        return $readyStepIds;
    }

    /**
     * @param  array<string, StepSnapshot>  $byKey
     * @param  array<string, StepStatus>  $statuses
     */
    private function dependenciesSatisfied(StepSnapshot $snapshot, array $byKey, array $statuses): bool
    {
        foreach ($snapshot->dependsOn as $dependency) {
            if (!$this->isSatisfied($statuses[$dependency], $byKey[$dependency])) {
                return false;
            }
        }

        return true;
    }

    /** A dependency is satisfied when it succeeded, or failed/skipped while allow_failure. */
    private function isSatisfied(StepStatus $status, StepSnapshot $dependency): bool
    {
        if ($status->isSuccessful()) {
            return true;
        }

        return $dependency->allowFailure
            && in_array($status, [StepStatus::Failed, StepStatus::Skipped], true);
    }

    /**
     * The implicit stage gate: nothing in a later stage starts while an earlier
     * stage still has work in flight.
     *
     * @param  list<StepSnapshot>  $snapshots
     * @param  array<string, StepStatus>  $statuses
     */
    private function earlierStageStillActive(StepSnapshot $snapshot, array $snapshots, array $statuses): bool
    {
        foreach ($snapshots as $other) {
            if ($other->stagePosition >= $snapshot->stagePosition) {
                continue;
            }

            if (!$statuses[$other->stepKey]->isTerminal()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<StepSnapshot>  $snapshots
     * @param  array<string, StepStatus>  $statuses
     */
    private function finalStatus(array $snapshots, array $statuses): RunStatus
    {
        $hasSoftProblem = false;

        foreach ($snapshots as $snapshot) {
            $status = $statuses[$snapshot->stepKey];

            if ($status === StepStatus::Failed && !$snapshot->allowFailure) {
                return RunStatus::Failed;
            }

            if ($status === StepStatus::Failed || $status === StepStatus::Skipped) {
                $hasSoftProblem = true;
            }
        }

        return $hasSoftProblem ? RunStatus::Warning : RunStatus::Succeeded;
    }
}
