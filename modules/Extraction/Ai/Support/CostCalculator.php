<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Support;

final class CostCalculator
{
    /**
     * Converts reported token usage into USD micros using the rates in
     * `extraction.ai.pricing`. An unpriced model yields 0 rather than throwing —
     * a missing price row must never break document processing.
     */
    public function usdMicros(string $model, int $inputTokens, int $outputTokens, int $cachedInputTokens = 0): int
    {
        /** @var array<string, array{input: float, output: float, cached_input?: float}> $pricing */
        $pricing = config('extraction.ai.pricing', []);

        if (!isset($pricing[$model])) {
            return 0;
        }

        $rates = $pricing[$model];

        $usd = ($inputTokens / 1_000_000) * $rates['input']
            + ($outputTokens / 1_000_000) * $rates['output']
            + ($cachedInputTokens / 1_000_000) * ($rates['cached_input'] ?? $rates['input']);

        return (int) round($usd * 1_000_000);
    }
}
