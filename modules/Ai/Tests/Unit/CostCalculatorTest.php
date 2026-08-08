<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Unit;

use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\Services\CostCalculator;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\Ai\ValueObjects\AiUsage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CostCalculatorTest extends TestCase
{
    private function calculator(): CostCalculator
    {
        $registry = new ProviderRegistry;
        $registry->register(new FakeAiProvider);

        return new CostCalculator($registry);
    }

    #[Test]
    public function it_prices_a_call_from_the_model_descriptor(): void
    {
        // fake-model: $5 / MTok in, $25 / MTok out.
        // 1,000,000 in + 100,000 out = $5.00 + $2.50 = $7.50 = 7_500_000 micros.
        $this->assertSame(
            7_500_000,
            $this->calculator()->usdMicros('fake', 'fake-model', 1_000_000, 100_000),
        );
    }

    #[Test]
    public function cached_input_is_priced_at_the_cached_rate(): void
    {
        // 1,000,000 cached in at $0.50 / MTok = 500_000 micros.
        $this->assertSame(
            500_000,
            $this->calculator()->usdMicros('fake', 'fake-model', 0, 0, 1_000_000),
        );
    }

    #[Test]
    public function an_unknown_model_costs_zero_rather_than_throwing(): void
    {
        $this->assertSame(
            0,
            $this->calculator()->usdMicros('fake', 'some-future-model', 1_000_000, 1_000_000),
        );
    }

    #[Test]
    public function an_unknown_provider_costs_zero_rather_than_throwing(): void
    {
        $this->assertSame(
            0,
            $this->calculator()->usdMicros('not-a-vendor', 'fake-model', 1_000_000, 1_000_000),
        );
    }

    #[Test]
    public function priced_returns_a_usage_copy_with_the_cost_filled_in(): void
    {
        $usage = new AiUsage(inputTokens: 1_000_000, outputTokens: 100_000);

        $priced = $this->calculator()->priced('fake', 'fake-model', $usage);

        $this->assertSame(7_500_000, $priced->costUsdMicros);
        $this->assertSame(1_000_000, $priced->inputTokens);
        $this->assertSame(0, $usage->costUsdMicros, 'the original usage must not be mutated');
    }
}
