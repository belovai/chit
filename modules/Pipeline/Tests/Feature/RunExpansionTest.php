<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Exceptions\InvalidExpansionException;
use Modules\Pipeline\Services\RunExpander;
use Modules\Pipeline\Tests\Support\RegistersFakePipelines;
use Modules\Pipeline\Tests\Support\Steps\FakeExpandedStep;
use Modules\Pipeline\Tests\Support\Steps\FakeSuccessStep;
use Modules\Pipeline\ValueObjects\StepDefinition;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RunExpansionTest extends TestCase
{
    use RefreshDatabase, RegistersFakePipelines;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerFakePipelines();
    }

    #[Test]
    public function an_expanding_step_injects_a_new_step_that_then_runs(): void
    {
        $user = User::factory()->create();

        // fake_expandable: alpha:fake_expanding -> (beta filled by expansion)
        //                  -> review:fake_gate (auto passed) -> gamma:fake_success
        $run = app(StartRun::class)->handle('fake_expandable', $user->id, config: [
            'fake_gate' => ['auto_pass' => true],
        ]);
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertArrayHasKey('fake_expanded', $steps);
        $this->assertSame(StepStatus::Succeeded, $steps['fake_expanded']->status);
        $this->assertSame('beta', $steps['fake_expanded']->stage);
        $this->assertSame(1, $steps['fake_expanded']->stage_position);
        $this->assertSame(RunStatus::Succeeded, $run->status);
    }

    #[Test]
    public function an_injected_step_records_which_step_added_it(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_expandable', $user->id, config: [
            'fake_gate' => ['auto_pass' => true],
        ]);
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(
            $steps['fake_expanding']->id,
            $steps['fake_expanded']->added_by_step_id,
        );
    }

    #[Test]
    public function the_gate_stage_waits_for_the_dynamically_added_step_without_naming_it(): void
    {
        $user = User::factory()->create();

        // fake_gate declares no depends_on at all. The implicit stage gate must
        // still hold it until the expansion-added `beta` step is terminal.
        $run = app(StartRun::class)->handle('fake_expandable', $user->id, config: [
            'fake_gate' => ['auto_pass' => true],
        ]);
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertTrue(
            $steps['fake_expanded']->finished_at <= $steps['fake_gate']->finished_at,
        );
    }

    #[Test]
    public function expanding_into_an_undeclared_stage_fails_the_expanding_step(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_expandable', $user->id, config: [
            'fake_expanding' => ['target_stage' => 'nonexistent'],
        ]);
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(StepStatus::Failed, $steps['fake_expanding']->status);
        $this->assertStringContainsString('nonexistent', (string) $steps['fake_expanding']->error['message']);
        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertArrayNotHasKey('fake_expanded', $steps);
    }

    #[Test]
    public function expanding_with_an_unknown_dependency_fails_the_expanding_step(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_expandable', $user->id, config: [
            'fake_expanding' => ['expanded_depends_on' => ['ghost']],
        ]);
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(StepStatus::Failed, $steps['fake_expanding']->status);
        $this->assertArrayNotHasKey('fake_expanded', $steps);
    }

    #[Test]
    public function expanding_with_a_step_key_already_in_the_run_is_rejected(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id);
        $step = $run->currentSteps()->firstWhere('step_key', 'fake_success');
        $this->assertNotNull($step);

        $this->expectException(InvalidExpansionException::class);
        $this->expectExceptionMessage('fake_success');

        app(RunExpander::class)->expand($step, [
            StepDefinition::make(FakeSuccessStep::class)->inStage('gamma'),
        ]);
    }

    #[Test]
    public function expanding_with_a_dependency_cycle_is_rejected(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id);
        $step = $run->currentSteps()->firstWhere('step_key', 'fake_success');
        $this->assertNotNull($step);

        // fake_expanded depends on fake_skipping, and we also rewrite
        // fake_skipping to depend on fake_expanded -> cycle.
        $run->currentSteps()->firstWhere('step_key', 'fake_skipping')
            ?->update(['depends_on' => ['fake_expanded']]);

        $this->expectException(InvalidExpansionException::class);
        $this->expectExceptionMessage('cycle');

        app(RunExpander::class)->expand($step, [
            StepDefinition::make(FakeExpandedStep::class)->inStage('gamma')->dependsOn('fake_skipping'),
        ]);
    }
}
