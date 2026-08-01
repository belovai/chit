<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Actions\ResumeRun;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Exceptions\RunNotAwaitingManualException;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Tests\Support\RegistersFakePipelines;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GateTest extends TestCase
{
    use RefreshDatabase, RegistersFakePipelines;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerFakePipelines();
    }

    #[Test]
    public function a_holding_gate_parks_the_run_and_leaves_later_steps_pending(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_expandable', $user->id);
        $run->refresh();

        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(RunStatus::AwaitingManual, $run->status);
        $this->assertSame(StepStatus::AwaitingManual, $steps['fake_gate']->status);
        $this->assertNull($steps['fake_gate']->finished_at, 'a held gate is still open');
        $this->assertSame(StepStatus::Pending, $steps['fake_success']->status);
        $this->assertNotNull($run->expires_at);
    }

    #[Test]
    public function a_holding_gate_writes_its_review_request_artifact_and_findings(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_expandable', $user->id);

        $artifact = PipelineArtifact::query()
            ->where('run_id', $run->id)
            ->where('key', 'review_request')
            ->whereNull('superseded_at')
            ->first();

        $this->assertNotNull($artifact);
        $this->assertSame(['fields' => ['total_amount']], $artifact->payload);

        $gate = $run->fresh()?->currentSteps()->firstWhere('step_key', 'fake_gate');
        $this->assertSame('fake_blocker', $gate?->findings[0]['code']);
        $this->assertSame('blocker', $gate?->findings[0]['severity']);
    }

    #[Test]
    public function approving_resumes_the_run_to_completion(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_expandable', $user->id);

        app(ResumeRun::class)->approve($run->refresh(), [
            new PendingArtifact('review_decision', ArtifactKind::Json, ['decision' => 'approve']),
        ]);

        $run->refresh();
        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame(StepStatus::Succeeded, $steps['fake_gate']->status);
        $this->assertNotNull($steps['fake_gate']->finished_at);
        $this->assertSame(StepStatus::Succeeded, $steps['fake_success']->status);
        $this->assertNull($run->expires_at);
    }

    #[Test]
    public function rejecting_cancels_the_run_and_never_runs_later_steps(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_expandable', $user->id);

        app(ResumeRun::class)->reject($run->refresh(), [
            new PendingArtifact('review_decision', ArtifactKind::Json, ['decision' => 'reject']),
        ]);

        $run->refresh();
        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(RunStatus::Canceled, $run->status);
        $this->assertSame(StepStatus::Canceled, $steps['fake_gate']->status);
        $this->assertSame(StepStatus::Canceled, $steps['fake_success']->status);
        $this->assertNotNull($run->finished_at);
    }

    #[Test]
    public function the_rejection_decision_artifact_survives(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_expandable', $user->id);

        app(ResumeRun::class)->reject($run->refresh(), [
            new PendingArtifact('review_decision', ArtifactKind::Json, ['decision' => 'reject']),
        ]);

        $this->assertSame(
            ['decision' => 'reject'],
            PipelineArtifact::query()
                ->where('run_id', $run->id)
                ->where('key', 'review_decision')
                ->whereNull('superseded_at')
                ->first()?->payload,
        );
    }

    #[Test]
    public function a_gate_that_passes_its_policy_never_pauses_the_run(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('fake_expandable', $user->id, config: [
            'fake_gate' => ['auto_pass' => true],
        ]);
        $run->refresh();

        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertNull($run->expires_at);
    }

    #[Test]
    public function resuming_a_run_that_is_not_awaiting_a_human_is_rejected(): void
    {
        $user = User::factory()->create();
        $run = app(StartRun::class)->handle('fake_linear', $user->id);

        $this->expectException(RunNotAwaitingManualException::class);

        app(ResumeRun::class)->approve($run->refresh());
    }
}
