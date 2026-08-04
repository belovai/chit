<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Unit;

use Modules\Extraction\Enums\DocumentType;
use Modules\Receipt\Services\GatePolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GatePolicyTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function findings(string ...$codes): array
    {
        return array_map(
            // The severity a step proposes is deliberately wrong here — the
            // policy must take its severity from config, not from the finding.
            static fn (string $code): array => ['code' => $code, 'severity' => 'info', 'message' => null, 'context' => []],
            $codes,
        );
    }

    #[Test]
    public function a_clean_run_with_high_confidence_passes(): void
    {
        config()->set('receipt.gate.max_warnings', 1);

        $decision = app(GatePolicy::class)->evaluate([], 0.95, DocumentType::Receipt);

        $this->assertTrue($decision->passes);
    }

    #[Test]
    public function any_blocker_stops_the_run(): void
    {
        config()->set('receipt.gate.max_warnings', 5);

        $decision = app(GatePolicy::class)->evaluate($this->findings('total_missing'), 0.99, DocumentType::Receipt);

        $this->assertFalse($decision->passes);
        $this->assertSame(['total_missing'], $decision->blockers);
    }

    #[Test]
    public function the_config_severity_overrides_what_the_step_proposed(): void
    {
        config()->set('receipt.gate.max_warnings', 5);
        config()->set('receipt.gate.severity.new_merchant', 'blocker');

        $decision = app(GatePolicy::class)->evaluate($this->findings('new_merchant'), 0.99, DocumentType::Receipt);

        $this->assertFalse($decision->passes);
    }

    #[Test]
    public function warnings_stop_the_run_only_above_the_configured_budget(): void
    {
        config()->set('receipt.gate.max_warnings', 1);
        $policy = app(GatePolicy::class);

        $this->assertTrue($policy->evaluate($this->findings('new_merchant'), 0.99, DocumentType::Receipt)->passes);
        $this->assertFalse(
            $policy->evaluate($this->findings('new_merchant', 'low_ocr_confidence'), 0.99, DocumentType::Receipt)->passes,
        );
    }

    #[Test]
    public function confidence_below_the_type_threshold_stops_the_run(): void
    {
        config()->set('receipt.gate.max_warnings', 5);
        config()->set('receipt.gate.min_confidence.receipt', 0.75);

        $decision = app(GatePolicy::class)->evaluate([], 0.60, DocumentType::Receipt);

        $this->assertFalse($decision->passes);
        $this->assertSame('low_confidence', $decision->reason);
    }

    #[Test]
    public function each_document_type_gets_its_own_threshold(): void
    {
        config()->set('receipt.gate.max_warnings', 5);
        config()->set('receipt.gate.min_confidence', ['receipt' => 0.75, 'utility_bill' => 0.90]);
        $policy = app(GatePolicy::class);

        $this->assertTrue($policy->evaluate([], 0.85, DocumentType::Receipt)->passes);
        $this->assertFalse($policy->evaluate([], 0.85, DocumentType::UtilityBill)->passes);
    }

    #[Test]
    public function info_findings_never_count_against_the_budget(): void
    {
        config()->set('receipt.gate.max_warnings', 0);

        $decision = app(GatePolicy::class)->evaluate($this->findings('no_previous_bill'), 0.99, DocumentType::UtilityBill);

        $this->assertTrue($decision->passes);
    }

    #[Test]
    public function an_unknown_finding_code_is_treated_as_a_warning(): void
    {
        config()->set('receipt.gate.max_warnings', 0);

        $decision = app(GatePolicy::class)->evaluate($this->findings('brand_new_code'), 0.99, DocumentType::Receipt);

        $this->assertFalse($decision->passes);
        $this->assertSame(['brand_new_code'], $decision->warnings);
    }
}
