<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Unit;

use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EnumTest extends TestCase
{
    #[Test]
    public function run_status_terminal_states_are_terminal(): void
    {
        foreach ([RunStatus::Succeeded, RunStatus::Warning, RunStatus::Failed, RunStatus::Canceled, RunStatus::Expired] as $status) {
            $this->assertTrue($status->isTerminal(), $status->value.' should be terminal');
        }
    }

    #[Test]
    public function run_status_active_states_are_not_terminal(): void
    {
        foreach ([RunStatus::Queued, RunStatus::Running, RunStatus::AwaitingManual] as $status) {
            $this->assertFalse($status->isTerminal(), $status->value.' should not be terminal');
        }
    }

    #[Test]
    public function step_status_awaiting_manual_is_not_terminal(): void
    {
        $this->assertFalse(StepStatus::AwaitingManual->isTerminal());
    }

    #[Test]
    public function step_status_terminal_states_are_terminal(): void
    {
        foreach ([StepStatus::Succeeded, StepStatus::Failed, StepStatus::Skipped, StepStatus::Canceled, StepStatus::Expired] as $status) {
            $this->assertTrue($status->isTerminal(), $status->value.' should be terminal');
        }
    }

    #[Test]
    public function only_succeeded_is_successful(): void
    {
        $this->assertTrue(StepStatus::Succeeded->isSuccessful());
        $this->assertFalse(StepStatus::Failed->isSuccessful());
        $this->assertFalse(StepStatus::Skipped->isSuccessful());
    }
}
