<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\ValueObjects;

final readonly class ExtractionResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public ExtractedReceipt|ExtractedBill $document,
        public float $confidence,
        public AiUsage $usage,
        public array $rawResponse,
    ) {}
}
