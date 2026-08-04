<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;
use Modules\Transaction\Models\Transaction;

/**
 * The content-level duplicate: the same purchase photographed twice, or a
 * reprint. dedupe_file_hash cannot see these because the bytes differ.
 * Runs on both branches; the bill branch keys on the billing period instead.
 */
final class DedupeContentStep implements PipelineStep
{
    public static function key(): string
    {
        return 'dedupe_content';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $merchantId = $context->artifact('merchant_candidates')->json()['accepted_id'] ?? null;

        // Without a resolved merchant there is nothing precise to compare
        // against, and a loose match here would fire on every second receipt.
        if ($merchantId === null) {
            return StepResult::success();
        }

        $isBill = ArtifactCodec::documentArtifactKey($context) === 'extracted_bill';
        $document = $isBill ? ArtifactCodec::readBill($context) : ArtifactCodec::readReceipt($context);

        $date = $document instanceof ExtractedReceipt ? $document->occurredAt : $document->periodEnd;
        $total = $document->totalMinor;

        if ($date === null || $total === null) {
            return StepResult::success();
        }

        $existing = Transaction::query()
            ->where('owner_id', $context->ownerId())
            ->where('merchant_id', $merchantId)
            ->whereDate('occurred_at', $date->toDateString())
            ->whereRaw('round(total_amount * 100) = ?', [$total])
            ->first();

        if ($existing === null) {
            return StepResult::success();
        }

        return StepResult::success()->finding(Finding::blocker('possible_duplicate', context: [
            'transaction_hash_id' => $existing->hash_id,
            'occurred_at' => $existing->occurred_at->toDateString(),
            'total_minor' => $total,
        ]));
    }
}
