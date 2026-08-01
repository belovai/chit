<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Jobs\ExecuteStep;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Tests\Support\RegistersFakePipelines;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RunExecutionTest extends TestCase
{
    use RefreshDatabase, RegistersFakePipelines;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerFakePipelines();
    }

    #[Test]
    public function a_hard_failure_mid_pipeline_fails_the_run_and_skips_the_rest(): void
    {
        $user = User::factory()->create();

        // fake_linear: fake_success -> fake_failing (permanent) -> fake_skipping
        $run = app(StartRun::class)->handle('fake_linear', $user->id);
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertSame(StepStatus::Succeeded, $steps['fake_success']->status);
        $this->assertSame(StepStatus::Failed, $steps['fake_failing']->status);
        $this->assertSame(StepStatus::Skipped, $steps['fake_skipping']->status);
        $this->assertSame('fake_failing', $run->error_summary['step_key']);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->duration_ms);
    }

    #[Test]
    public function a_succeeding_step_writes_its_artifacts(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_linear', $user->id);

        $artifact = PipelineArtifact::query()
            ->where('run_id', $run->id)
            ->where('key', 'fake_success_output')
            ->whereNull('superseded_at')
            ->first();

        $this->assertNotNull($artifact);
        $this->assertSame(['ok' => true], $artifact->payload);
    }

    #[Test]
    public function a_retryable_failure_creates_a_second_attempt_that_succeeds(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_retry', $user->id, config: [
            'fake_failing' => ['retryable' => true, 'succeed_from_attempt' => 2],
        ]);
        $run->refresh();

        $attempts = $run->steps()->where('step_key', 'fake_failing')->orderBy('attempt')->get();

        $this->assertCount(2, $attempts);
        $this->assertSame(StepStatus::Failed, $attempts[0]->status);
        $this->assertSame(StepStatus::Succeeded, $attempts[1]->status);
        $this->assertSame(RunStatus::Succeeded, $run->status);
    }

    #[Test]
    public function a_permanent_failure_is_not_retried_even_with_attempts_left(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_retry', $user->id, config: [
            'fake_failing' => ['retryable' => false],
        ]);
        $run->refresh();

        $this->assertCount(1, $run->steps()->where('step_key', 'fake_failing')->get());
        // fake_retry marks that step allow_failure, so the run continues and warns.
        $this->assertSame(RunStatus::Warning, $run->status);
        $this->assertSame(
            StepStatus::Succeeded,
            $run->currentSteps()->firstWhere('step_key', 'fake_success')?->status,
        );
    }

    #[Test]
    public function exhausting_max_attempts_stops_retrying(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_retry', $user->id, config: [
            'fake_failing' => ['retryable' => true],
        ]);
        $run->refresh();

        $this->assertCount(3, $run->steps()->where('step_key', 'fake_failing')->get());
        $this->assertSame(RunStatus::Warning, $run->status);
    }

    #[Test]
    public function execute_step_is_idempotent_for_an_already_terminal_row(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id);
        $step = $run->currentSteps()->firstWhere('step_key', 'fake_success');
        $this->assertNotNull($step);
        $finishedAt = $step->finished_at;

        ExecuteStep::dispatch($step->id);

        $step->refresh();
        $this->assertSame(StepStatus::Succeeded, $step->status);
        $this->assertEquals($finishedAt, $step->finished_at, 'a redelivered job must not re-run the step');
        $this->assertSame(
            1,
            PipelineArtifact::query()
                ->where('run_id', $run->id)
                ->where('key', 'fake_success_output')
                ->whereNull('superseded_at')
                ->count(),
        );
    }

    #[Test]
    public function it_dispatches_each_step_onto_the_queue_the_step_class_declares(): void
    {
        Bus::fake([ExecuteStep::class]);
        $user = User::factory()->create();

        app(StartRun::class)->handle('fake_linear', $user->id);

        Bus::assertDispatched(ExecuteStep::class, fn (ExecuteStep $job): bool => $job->queue === 'sync');
    }
}
