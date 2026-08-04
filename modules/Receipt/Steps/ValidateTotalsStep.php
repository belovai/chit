<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Carbon\CarbonImmutable;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * Runs on both branches. The arithmetic check is the cheapest reliable signal
 * that OCR misread a digit: a wrong character in a price almost always breaks
 * the sum, while a wrong character in a product name breaks nothing.
 */
final class ValidateTotalsStep implements PipelineStep
{
    public static function key(): string
    {
        return 'validate_totals';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $document = ArtifactCodec::documentArtifactKey($context) === 'extracted_bill'
            ? ArtifactCodec::readBill($context)
            : ArtifactCodec::readReceipt($context);

        $result = StepResult::success();

        $total = $document->totalMinor;

        if ($total === null || $total <= 0) {
            $result->finding(Finding::blocker('total_missing'));
        }

        if ($document instanceof ExtractedReceipt && $total !== null && $document->items !== []) {
            $tolerance = (int) config('receipt.validation.sum_tolerance_minor');
            $delta = abs($document->itemsTotalMinor() - (int) $document->discountMinor - $total);

            if ($delta > $tolerance) {
                $result->finding(Finding::blocker('line_items_sum_mismatch', context: [
                    'items_total_minor' => $document->itemsTotalMinor(),
                    'header_total_minor' => $total,
                    'delta_minor' => $delta,
                ]));
            }
        }

        $date = $document instanceof ExtractedReceipt ? $document->occurredAt : $document->issuedAt;

        if ($date !== null && $date->isAfter(CarbonImmutable::now()->addDay())) {
            $result->finding(Finding::blocker('date_in_future', context: ['date' => $date->toDateString()]));
        }

        return $result;
    }
}
