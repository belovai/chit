<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Unit;

use LogicException;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Services\ReadyStepResolver;
use Modules\Pipeline\ValueObjects\StepSnapshot;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReadyStepResolverTest extends TestCase
{
    private ReadyStepResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ReadyStepResolver;
    }

    /**
     * @param  list<string>  $dependsOn
     */
    private function snapshot(
        int $id,
        string $key,
        int $stagePosition,
        StepStatus $status,
        array $dependsOn = [],
        bool $allowFailure = false,
    ): StepSnapshot {
        return new StepSnapshot($id, $key, $stagePosition, $status, $dependsOn, $allowFailure);
    }

    #[Test]
    public function a_step_with_no_dependencies_in_the_first_stage_is_ready(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Pending),
            $this->snapshot(2, 'b', 1, StepStatus::Pending, ['a']),
        ]);

        $this->assertSame([1], $plan->readyStepIds);
        $this->assertFalse($plan->isComplete);
    }

    #[Test]
    public function a_step_waits_for_its_dependency(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Running),
            $this->snapshot(2, 'b', 1, StepStatus::Pending, ['a']),
        ]);

        $this->assertSame([], $plan->readyStepIds);
        $this->assertFalse($plan->isComplete);
    }

    #[Test]
    public function a_step_becomes_ready_once_its_dependency_succeeds(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Succeeded),
            $this->snapshot(2, 'b', 1, StepStatus::Pending, ['a']),
        ]);

        $this->assertSame([2], $plan->readyStepIds);
    }

    #[Test]
    public function an_earlier_stage_still_running_blocks_a_later_stage_even_without_a_dependency(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Running),
            // no depends_on at all — the implicit stage gate must still hold it
            $this->snapshot(2, 'gate', 1, StepStatus::Pending),
        ]);

        $this->assertSame([], $plan->readyStepIds);
    }

    #[Test]
    public function two_steps_in_the_same_stage_are_both_ready(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Pending),
            $this->snapshot(2, 'b', 0, StepStatus::Pending),
        ]);

        $this->assertSame([1, 2], $plan->readyStepIds);
    }

    #[Test]
    public function a_hard_failed_dependency_skips_its_dependent(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Failed),
            $this->snapshot(2, 'b', 1, StepStatus::Pending, ['a']),
        ]);

        $this->assertSame([], $plan->readyStepIds);
        $this->assertSame([2], $plan->skipStepIds);
    }

    #[Test]
    public function skips_cascade_transitively(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Failed),
            $this->snapshot(2, 'b', 1, StepStatus::Pending, ['a']),
            $this->snapshot(3, 'c', 2, StepStatus::Pending, ['b']),
        ]);

        $this->assertSame([2, 3], $plan->skipStepIds);
        $this->assertTrue($plan->isComplete);
        $this->assertSame(RunStatus::Failed, $plan->finalStatus);
    }

    #[Test]
    public function an_allow_failure_dependency_lets_its_dependent_run(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Failed, allowFailure: true),
            $this->snapshot(2, 'b', 1, StepStatus::Pending, ['a']),
        ]);

        $this->assertSame([2], $plan->readyStepIds);
        $this->assertSame([], $plan->skipStepIds);
    }

    #[Test]
    public function a_run_with_only_successes_finishes_succeeded(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Succeeded),
            $this->snapshot(2, 'b', 1, StepStatus::Succeeded, ['a']),
        ]);

        $this->assertTrue($plan->isComplete);
        $this->assertSame(RunStatus::Succeeded, $plan->finalStatus);
    }

    #[Test]
    public function an_allow_failure_bukas_finishes_the_run_as_warning(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Failed, allowFailure: true),
            $this->snapshot(2, 'b', 1, StepStatus::Succeeded, ['a']),
        ]);

        $this->assertTrue($plan->isComplete);
        $this->assertSame(RunStatus::Warning, $plan->finalStatus);
    }

    #[Test]
    public function a_self_skipped_step_finishes_the_run_as_warning(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Skipped),
        ]);

        $this->assertTrue($plan->isComplete);
        $this->assertSame(RunStatus::Warning, $plan->finalStatus);
    }

    #[Test]
    public function an_awaiting_manual_step_keeps_the_run_open(): void
    {
        $plan = $this->resolver->resolve([
            $this->snapshot(1, 'gate', 0, StepStatus::AwaitingManual),
            $this->snapshot(2, 'after', 1, StepStatus::Pending, ['gate']),
        ]);

        $this->assertSame([], $plan->readyStepIds);
        $this->assertFalse($plan->isComplete);
        $this->assertTrue($plan->isAwaitingManual);
        $this->assertNull($plan->finalStatus);
    }

    #[Test]
    public function it_throws_when_a_dependency_key_is_missing_from_the_run(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('depends on unknown step [ghost]');

        $this->resolver->resolve([
            $this->snapshot(1, 'a', 0, StepStatus::Pending, ['ghost']),
        ]);
    }
}
