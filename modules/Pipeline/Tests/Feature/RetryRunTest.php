<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Actions\CancelRun;
use Modules\Pipeline\Actions\RetryRun;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\RetryMode;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\Pipeline\Exceptions\StepNotInRunException;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Tests\Support\RegistersFakePipelines;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RetryRunTest extends TestCase
{
    use RefreshDatabase, RegistersFakePipelines;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerFakePipelines();
    }

    #[Test]
    public function retrying_a_single_step_creates_a_second_attempt_for_that_step_only(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();
        $this->assertSame(RunStatus::Failed, $run->status);

        app(RetryRun::class)->handle($run, RetryMode::Single, 'fake_failing');
        $run->refresh();

        $this->assertCount(2, $run->steps()->where('step_key', 'fake_failing')->get());
        $this->assertCount(1, $run->steps()->where('step_key', 'fake_success')->get());
    }

    #[Test]
    public function retrying_a_single_step_that_now_succeeds_completes_the_run(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();

        $run->currentSteps()->firstWhere('step_key', 'fake_failing')
            ?->update(['config' => ['succeed_from_attempt' => 2]]);

        app(RetryRun::class)->handle($run, RetryMode::Single, 'fake_failing');
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');
        $this->assertSame(StepStatus::Succeeded, $steps['fake_failing']->status);
        $this->assertSame(StepStatus::Skipped, $steps['fake_skipping']->status);
        $this->assertSame(RunStatus::Warning, $run->status);
    }

    #[Test]
    public function retrying_from_a_step_also_reruns_everything_downstream(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();

        app(RetryRun::class)->handle($run, RetryMode::From, 'fake_success');
        $run->refresh();

        $this->assertCount(2, $run->steps()->where('step_key', 'fake_success')->get());
        $this->assertCount(2, $run->steps()->where('step_key', 'fake_failing')->get());
        $this->assertCount(2, $run->steps()->where('step_key', 'fake_skipping')->get());
    }

    #[Test]
    public function retrying_supersedes_the_artifacts_of_the_reran_steps(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();

        app(RetryRun::class)->handle($run, RetryMode::From, 'fake_success');

        $artifacts = PipelineArtifact::query()
            ->where('run_id', $run->id)
            ->where('key', 'fake_success_output')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $artifacts, 'the old artifact row must survive for comparison');
        $this->assertNotNull($artifacts[0]->superseded_at);
        $this->assertNull($artifacts[1]->superseded_at);
    }

    #[Test]
    public function retrying_the_whole_run_creates_a_new_run_linked_to_the_original(): void
    {
        $user = User::factory()->create();
        $original = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();

        $retry = app(RetryRun::class)->handle($original, RetryMode::All);

        $this->assertNotSame($original->id, $retry->id);
        $this->assertSame($original->id, $retry->retried_from_run_id);
        $this->assertSame(TriggerSource::Retry, $retry->trigger_source);
        $this->assertCount(3, $retry->currentSteps());
    }

    #[Test]
    public function retrying_the_whole_run_keeps_the_original_per_step_config(): void
    {
        $user = User::factory()->create();
        $original = app(StartRun::class)->handle('fake_linear', $user->id, config: [
            'fake_failing' => ['succeed_from_attempt' => 1],
        ])->refresh();

        $retry = app(RetryRun::class)->handle($original, RetryMode::All);

        $this->assertSame(
            1,
            $retry->currentSteps()->firstWhere('step_key', 'fake_failing')?->config['succeed_from_attempt'],
        );
    }

    #[Test]
    public function retrying_an_unknown_step_key_is_rejected(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();

        $this->expectException(StepNotInRunException::class);

        app(RetryRun::class)->handle($run, RetryMode::Single, 'ghost');
    }

    #[Test]
    public function cancelling_a_parked_run_cancels_every_open_step(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_expandable', $user->id)->refresh();
        $this->assertSame(RunStatus::AwaitingManual, $run->status);

        app(CancelRun::class)->handle($run);
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');
        $this->assertSame(RunStatus::Canceled, $run->status);
        $this->assertSame(StepStatus::Canceled, $steps['fake_gate']->status);
        $this->assertSame(StepStatus::Canceled, $steps['fake_success']->status);
        $this->assertNull($run->expires_at);
    }

    #[Test]
    public function cancelling_an_already_finished_run_changes_nothing(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id)->refresh();
        $finishedAt = $run->finished_at;

        app(CancelRun::class)->handle($run);
        $run->refresh();

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertEquals($finishedAt, $run->finished_at);
    }
}
