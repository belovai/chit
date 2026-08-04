<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\ValueObjects;

/** Amounts are minor units (fillér/cent) — never floats. See the plan header. */
final readonly class ExtractedLineItem
{
    public function __construct(
        public string $description,
        public float $quantity,
        public ?string $unit,
        public int $unitPriceMinor,
        public ?int $totalMinor = null,
    ) {}

    /** The printed line total when present, otherwise quantity × unit price. */
    public function effectiveTotalMinor(): int
    {
        return $this->totalMinor ?? (int) round($this->quantity * $this->unitPriceMinor);
    }
}
