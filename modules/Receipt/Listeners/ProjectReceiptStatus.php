<?php

declare(strict_types=1);

namespace Modules\Receipt\Listeners;

use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Events\RunStatusChanged;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;

/**
 * The single writer of `receipts.status`. Nothing else may set that column —
 * it is a cached projection of the run, kept as a column only so the receipt
 * list can filter and sort on it.
 */
final class ProjectReceiptStatus
{
    public function handle(RunStatusChanged $event): void
    {
        $run = $event->run;

        if ($run->subject_type !== Receipt::class) {
            return;
        }

        $receipt = Receipt::query()->find($run->subject_id);

        if ($receipt === null || $receipt->current_run_id !== $run->id) {
            // A stale run (e.g. a superseded retry) must not overwrite the
            // status the receipt's current run has already projected.
            return;
        }

        $receipt->update(['status' => $this->project($run->id, $event->to)]);
    }

    private function project(int $runId, RunStatus $status): ReceiptStatus
    {
        return match ($status) {
            RunStatus::Queued => ReceiptStatus::Pending,
            RunStatus::Running => ReceiptStatus::Processing,
            RunStatus::AwaitingManual => ReceiptStatus::NeedsReview,
            RunStatus::Succeeded, RunStatus::Warning => ReceiptStatus::Approved,
            RunStatus::Failed => ReceiptStatus::Failed,
            RunStatus::Expired => ReceiptStatus::Canceled,
            // A canceled run means "rejected by a human" when a review decision
            // was recorded, and a plain cancel otherwise.
            RunStatus::Canceled => $this->wasReviewed($runId)
                ? ReceiptStatus::Rejected
                : ReceiptStatus::Canceled,
        };
    }

    private function wasReviewed(int $runId): bool
    {
        return PipelineArtifact::query()
            ->where('run_id', $runId)
            ->where('key', 'review_decision')
            ->exists();
    }
}
