<?php

declare(strict_types=1);

namespace Modules\Extraction\Tests\Unit;

use Modules\Extraction\Ai\Support\CostCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CostCalculatorTest extends TestCase
{
    #[Test]
    public function it_prices_a_call_from_the_configured_rates(): void
    {
        // claude-opus-5: $5 / MTok in, $25 / MTok out.
        // 1,000,000 in + 100,000 out = $5.00 + $2.50 = $7.50 = 7_500_000 micros.
        $micros = app(CostCalculator::class)->usdMicros('claude-opus-5', 1_000_000, 100_000, 0);

        $this->assertSame(7_500_000, $micros);
    }

    #[Test]
    public function cached_input_is_priced_at_the_cached_rate(): void
    {
        // 0 fresh in, 1,000,000 cached in at $0.50 / MTok, 0 out = 500_000 micros.
        $micros = app(CostCalculator::class)->usdMicros('claude-opus-5', 0, 0, 1_000_000);

        $this->assertSame(500_000, $micros);
    }

    #[Test]
    public function an_unpriced_model_costs_zero_rather_than_throwing(): void
    {
        $this->assertSame(0, app(CostCalculator::class)->usdMicros('some-future-model', 1_000_000, 1_000_000, 0));
    }
}
