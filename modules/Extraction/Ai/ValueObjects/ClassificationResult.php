<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\ValueObjects;

use Modules\Extraction\Enums\DocumentType;

final readonly class ClassificationResult
{
    /**
     * @param  array<string, mixed>  $rawResponse  kept verbatim for the audit trail
     */
    public function __construct(
        public DocumentType $type,
        public float $confidence,
        public AiUsage $usage,
        public array $rawResponse,
    ) {}
}
