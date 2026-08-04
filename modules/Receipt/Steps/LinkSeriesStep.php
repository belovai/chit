<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Extraction\Ai\Support\DocumentMapper;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * Finds the previous bill in the same series so anomaly_check has something to
 * compare against. Only approved bills count — comparing against an unreviewed
 * one would propagate an extraction error into the check meant to catch it.
 */
final class LinkSeriesStep implements PipelineStep
{
    public static function key(): string
    {
        return 'link_series';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $receipt = ArtifactCodec::subject($context)->refresh();
        $empty = StepResult::success()->artifact('previous_bill', ['found' => null]);

        if ($receipt->series_key === null) {
            return $empty->finding(Finding::info('no_previous_bill', context: ['reason' => 'no_series_key']));
        }

        $previous = Receipt::query()
            ->where('owner_id', $context->ownerId())
            ->where('series_key', $receipt->series_key)
            ->where('status', ReceiptStatus::Approved->value)
            ->whereKeyNot($receipt->id)
            ->whereNotNull('current_run_id')
            ->orderByDesc('created_at')
            ->first();

        if ($previous === null) {
            return $empty->finding(Finding::info('no_previous_bill', context: ['reason' => 'first_in_series']));
        }

        $artifact = PipelineArtifact::query()
            ->where('run_id', $previous->current_run_id)
            ->where('key', 'extracted_bill')
            ->whereNull('superseded_at')
            ->first();

        if ($artifact === null) {
            return $empty->finding(Finding::info('no_previous_bill', context: ['reason' => 'artifact_pruned']));
        }

        /** @var array<string, mixed> $payload */
        $payload = $artifact->payload['payload'] ?? [];
        $bill = DocumentMapper::toBill($payload);

        return StepResult::success()->artifact('previous_bill', [
            'found' => true,
            'receipt_hash_id' => $previous->hash_id,
            'meter_reading' => $bill->meterReading,
            'consumption' => $bill->consumption,
            'consumption_unit' => $bill->consumptionUnit,
            'period_end' => $bill->periodEnd?->toDateString(),
            'total_minor' => $bill->totalMinor,
        ]);
    }
}
