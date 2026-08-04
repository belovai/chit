<?php

declare(strict_types=1);

namespace Modules\Receipt\Listeners;

use Modules\Pipeline\Models\PipelineRun;
use Modules\Receipt\Models\Receipt;

/**
 * The single writer of `receipts.current_run_id`. A freshly created run
 * becomes "the" run for its receipt the instant it exists — that includes a
 * retry, which is exactly a new run for the same subject — so every listener
 * and step that guards on `current_run_id === run.id` (ProjectReceiptStatus,
 * ProjectReceiptFields, ReviewGateStep's finding collection) already sees the
 * right run from that run's very first step onward, not just once it finishes.
 */
final class ProjectReceiptCurrentRun
{
    public function handle(PipelineRun $run): void
    {
        if ($run->subject_type !== Receipt::class || $run->subject_id === null) {
            return;
        }

        Receipt::query()->whereKey($run->subject_id)->update(['current_run_id' => $run->id]);
    }
}
