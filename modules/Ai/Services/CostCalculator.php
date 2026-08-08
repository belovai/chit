<?php

declare(strict_types=1);

namespace Modules\Ai\Services;

use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\ValueObjects\AiUsage;

/**
 * Turns reported token usage into USD micros using the rates a provider
 * declares on its ModelDescriptor. An unpriced or unknown model yields 0
 * rather than throwing — a missing price row must never break processing.
 */
final class CostCalculator
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function usdMicros(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cachedInputTokens = 0,
    ): int {
        if (!$this->providers->has($provider)) {
            return 0;
        }

        $descriptor = $this->providers->get($provider)->model($model);

        if ($descriptor === null) {
            return 0;
        }

        $pricing = $descriptor->pricing;

        $usd = ($inputTokens / 1_000_000) * $pricing->inputPerMillion
            + ($outputTokens / 1_000_000) * $pricing->outputPerMillion
            + ($cachedInputTokens / 1_000_000) * $pricing->cachedInputPerMillion;

        return (int) round($usd * 1_000_000);
    }

    public function priced(string $provider, string $model, AiUsage $usage): AiUsage
    {
        return new AiUsage(
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cachedInputTokens: $usage->cachedInputTokens,
            costUsdMicros: $this->usdMicros(
                provider: $provider,
                model: $model,
                inputTokens: $usage->inputTokens,
                outputTokens: $usage->outputTokens,
                cachedInputTokens: $usage->cachedInputTokens,
            ),
        );
    }
}
