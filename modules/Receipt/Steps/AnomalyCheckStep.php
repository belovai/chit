<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Carbon\CarbonImmutable;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * The best OCR-error detector in the system. A dropped digit in a meter reading
 * sails through the arithmetic check — the totals still add up — but it cannot
 * survive a continuity check against the previous bill.
 *
 * Registered with allowFailure: a first bill in a series has nothing to compare
 * against, and that must warn, not fail the run.
 */
final class AnomalyCheckStep implements PipelineStep
{
    public static function key(): string
    {
        return 'anomaly_check';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $previous = $context->artifact('previous_bill')->json();

        if (($previous['found'] ?? null) !== true) {
            return StepResult::skipped('No previous bill in this series to compare against.');
        }

        $bill = ArtifactCodec::readBill($context);
        $result = StepResult::success();

        $previousReading = is_numeric($previous['meter_reading'] ?? null) ? (float) $previous['meter_reading'] : null;

        if ($bill->meterReading !== null && $previousReading !== null && $bill->meterReading < $previousReading) {
            $result->finding(Finding::blocker('meter_reading_decreased', context: [
                'current' => $bill->meterReading,
                'previous' => $previousReading,
            ]));
        }

        $previousConsumption = is_numeric($previous['consumption'] ?? null) ? (float) $previous['consumption'] : null;
        $factor = (float) config('receipt.anomaly.consumption_factor');

        if ($bill->consumption !== null && $previousConsumption !== null && $previousConsumption > 0.0
            && $bill->consumption > $previousConsumption * $factor) {
            $result->finding(Finding::warning('consumption_anomaly', context: [
                'current' => $bill->consumption,
                'previous' => $previousConsumption,
                'unit' => $bill->consumptionUnit,
                'factor' => $factor,
            ]));
        }

        $previousPeriodEnd = is_string($previous['period_end'] ?? null)
            ? CarbonImmutable::parse($previous['period_end'])
            : null;
        $gapDays = (int) config('receipt.anomaly.period_gap_days');

        if ($bill->periodStart !== null && $previousPeriodEnd !== null
            && $previousPeriodEnd->diffInDays($bill->periodStart) > $gapDays) {
            $result->finding(Finding::warning('period_gap', context: [
                'previous_period_end' => $previousPeriodEnd->toDateString(),
                'current_period_start' => $bill->periodStart->toDateString(),
            ]));
        }

        return $result;
    }
}
