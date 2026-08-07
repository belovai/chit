<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class ExtractedReceipt
{
    /**
     * @param  list<ExtractedLineItem>  $items
     */
    public function __construct(
        public ?string $merchantName,
        public ?string $merchantAddress,
        public ?CarbonImmutable $occurredAt,
        public ?string $currency,
        public ?int $totalMinor,
        public ?int $discountMinor,
        public ?string $paymentMethod,
        public array $items,
    ) {}

    public function itemsTotalMinor(): int
    {
        return array_sum(
            array_map(
                static fn (ExtractedLineItem $item): int => $item->effectiveTotalMinor(),
                $this->items,
            ),
        );
    }
}
