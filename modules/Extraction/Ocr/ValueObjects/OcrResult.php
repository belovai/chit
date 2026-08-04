<?php

declare(strict_types=1);

namespace Modules\Extraction\Ocr\ValueObjects;

final readonly class OcrResult
{
    /**
     * @param  list<float>  $pageConfidences  per-page mean confidence, 0.0–1.0
     */
    public function __construct(
        public string $text,
        public float $meanConfidence,
        public array $pageConfidences,
        public string $engine,
        public int $durationMs,
    ) {}
}
