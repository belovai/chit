<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Events\RunStatusChanged;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReceiptStatusProjectionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Receipt, 1: PipelineRun} */
    private function pair(): array
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user, 'owner')->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create([
            'subject_type' => Receipt::class,
            'subject_id' => $receipt->id,
        ]);
        $receipt->update(['current_run_id' => $run->id]);

        return [$receipt, $run];
    }

    #[Test]
    public function it_projects_each_run_status(): void
    {
        [$receipt, $run] = $this->pair();

        $cases = [
            [RunStatus::Running, ReceiptStatus::Processing],
            [RunStatus::AwaitingManual, ReceiptStatus::NeedsReview],
            [RunStatus::Succeeded, ReceiptStatus::Approved],
            [RunStatus::Warning, ReceiptStatus::Approved],
            [RunStatus::Failed, ReceiptStatus::Failed],
            [RunStatus::Expired, ReceiptStatus::Canceled],
        ];

        foreach ($cases as [$runStatus, $expected]) {
            RunStatusChanged::dispatch($run, RunStatus::Queued, $runStatus);
            $this->assertSame($expected, $receipt->refresh()->status, $runStatus->value);
        }
    }

    #[Test]
    public function a_canceled_run_with_a_review_decision_projects_as_rejected(): void
    {
        [$receipt, $run] = $this->pair();
        $step = PipelineRunStep::factory()->for($run, 'run')->create();
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'review_decision',
            'kind' => ArtifactKind::Json,
            'payload' => ['decision' => 'reject'],
        ]);

        RunStatusChanged::dispatch($run, RunStatus::AwaitingManual, RunStatus::Canceled);

        $this->assertSame(ReceiptStatus::Rejected, $receipt->refresh()->status);
    }

    #[Test]
    public function a_canceled_run_without_a_review_decision_projects_as_canceled(): void
    {
        [$receipt, $run] = $this->pair();

        RunStatusChanged::dispatch($run, RunStatus::Running, RunStatus::Canceled);

        $this->assertSame(ReceiptStatus::Canceled, $receipt->refresh()->status);
    }

    #[Test]
    public function a_run_that_is_no_longer_the_receipts_current_run_is_ignored(): void
    {
        [$receipt, $run] = $this->pair();
        $receipt->update(['status' => ReceiptStatus::Approved, 'current_run_id' => null]);

        RunStatusChanged::dispatch($run, RunStatus::Running, RunStatus::Failed);

        $this->assertSame(ReceiptStatus::Approved, $receipt->refresh()->status);
    }

    #[Test]
    public function a_run_whose_subject_is_not_a_receipt_is_ignored(): void
    {
        $run = PipelineRun::factory()->create();

        RunStatusChanged::dispatch($run, RunStatus::Queued, RunStatus::Failed);

        $this->assertTrue(true, 'no exception thrown');
    }
}
