<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\Pipeline\Jobs\AdvanceRun;
use Modules\Pipeline\Tests\Support\RegistersFakePipelines;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StartRunTest extends TestCase
{
    use RefreshDatabase, RegistersFakePipelines;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerFakePipelines();
    }

    #[Test]
    public function it_creates_a_queued_run_with_the_definition_frozen_in(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_linear', $user->id);

        $this->assertSame(RunStatus::Queued, $run->status);
        $this->assertSame('fake_linear', $run->definition_key);
        $this->assertSame(1, $run->definition_version);
        $this->assertSame(['alpha', 'beta', 'gamma'], $run->stages);
        $this->assertSame(TriggerSource::ManualUpload, $run->trigger_source);
        $this->assertNotNull($run->queued_at);
        $this->assertSame($user->id, $run->owner_id);
    }

    #[Test]
    public function it_creates_one_pending_step_row_per_definition_step(): void
    {
        // Prevent the post-transaction AdvanceRun dispatch from immediately
        // executing steps (sync queue in tests), so the rows are inspected
        // in the state StartRun itself left them in.
        Bus::fake([AdvanceRun::class]);
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_linear', $user->id);
        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertCount(3, $steps);
        $this->assertSame(StepStatus::Pending, $steps['fake_success']->status);
        $this->assertSame(1, $steps['fake_success']->attempt);
        $this->assertSame([], $steps['fake_success']->depends_on);
        $this->assertSame(['fake_success'], $steps['fake_failing']->depends_on);
    }

    #[Test]
    public function stage_position_follows_the_declared_stage_order_not_the_step_order(): void
    {
        $user = User::factory()->create();

        // FakeExpandablePipeline declares stages alpha, beta, review, gamma
        // but lists its steps as expanding(alpha), gate(review), success(gamma).
        $run = app(StartRun::class)->handle('fake_expandable', $user->id);
        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(0, $steps['fake_expanding']->stage_position);
        $this->assertSame(2, $steps['fake_gate']->stage_position);
        $this->assertSame(3, $steps['fake_success']->stage_position);
    }

    #[Test]
    public function it_copies_gate_allow_failure_attempts_and_config_onto_the_row(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_expandable', $user->id);
        $gate = $run->currentSteps()->firstWhere('step_key', 'fake_gate');

        $this->assertTrue($gate?->is_gate);
        $this->assertFalse($gate?->allow_failure);
        $this->assertSame(1, $gate?->max_attempts);
    }

    #[Test]
    public function it_attaches_the_subject_polymorphically(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_linear', $user->id, subject: $user);

        $this->assertSame($user::class, $run->subject_type);
        $this->assertSame($user->id, $run->subject_id);
    }

    #[Test]
    public function per_run_config_is_merged_onto_each_step(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle(
            'fake_linear',
            $user->id,
            config: ['fake_failing' => ['retryable' => true]],
        );

        $step = $run->currentSteps()->firstWhere('step_key', 'fake_failing');

        $this->assertTrue($step?->config['retryable']);
    }

    #[Test]
    public function it_records_the_run_it_was_retried_from(): void
    {
        $user = User::factory()->create();
        $original = app(StartRun::class)->handle('fake_linear', $user->id);

        $retry = app(StartRun::class)->handle(
            'fake_linear',
            $user->id,
            trigger: TriggerSource::Retry,
            retriedFromRunId: $original->id,
        );

        $this->assertSame($original->id, $retry->retried_from_run_id);
        $this->assertSame(TriggerSource::Retry, $retry->trigger_source);
    }
}
