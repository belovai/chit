<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class ExtractedBill
{
    public function __construct(
        public ?string $providerName,
        public ?string $customerReference,
        public ?string $currency,
        public ?int $totalMinor,
        public ?CarbonImmutable $issuedAt,
        public ?CarbonImmutable $periodStart,
        public ?CarbonImmutable $periodEnd,
        public ?float $meterReading,
        public ?float $previousMeterReading,
        public ?float $consumption,
        public ?string $consumptionUnit,
    ) {}
}
