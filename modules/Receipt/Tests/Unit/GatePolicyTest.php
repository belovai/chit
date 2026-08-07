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
            $policy->evaluate($this->findings('new_merchant', 'new_location'), 0.99, DocumentType::Receipt)->passes,
        );
    }

    #[Test]
    public function a_waived_warning_does_not_count_when_the_extraction_is_confident(): void
    {
        config()->set('receipt.gate.max_warnings', 0);
        config()->set('receipt.gate.min_confidence.receipt', 0.75);

        $decision = app(GatePolicy::class)->evaluate($this->findings('low_ocr_confidence'), 0.94, DocumentType::Receipt);

        $this->assertTrue($decision->passes);
        $this->assertSame([], $decision->warnings);
    }

    #[Test]
    public function a_waived_warning_still_counts_when_the_extraction_is_not_confident(): void
    {
        config()->set('receipt.gate.max_warnings', 0);
        config()->set('receipt.gate.min_confidence.receipt', 0.95);

        $decision = app(GatePolicy::class)->evaluate($this->findings('low_ocr_confidence'), 0.94, DocumentType::Receipt);

        $this->assertFalse($decision->passes);
        $this->assertSame(['low_ocr_confidence'], $decision->warnings);
    }

    #[Test]
    public function a_waived_warning_still_counts_when_the_type_has_no_threshold_to_clear(): void
    {
        config()->set('receipt.gate.max_warnings', 0);
        config()->set('receipt.gate.min_confidence', []);

        $decision = app(GatePolicy::class)->evaluate($this->findings('low_ocr_confidence'), 0.99, DocumentType::Unknown);

        $this->assertFalse($decision->passes);
        $this->assertSame(['low_ocr_confidence'], $decision->warnings);
    }

    #[Test]
    public function a_waivable_code_marked_a_blocker_still_blocks(): void
    {
        config()->set('receipt.gate.min_confidence.receipt', 0.75);
        config()->set('receipt.gate.severity.low_ocr_confidence', 'blocker');

        $decision = app(GatePolicy::class)->evaluate($this->findings('low_ocr_confidence'), 0.99, DocumentType::Receipt);

        $this->assertFalse($decision->passes);
        $this->assertSame(['low_ocr_confidence'], $decision->blockers);
    }

    #[Test]
    public function an_unknown_type_without_a_threshold_is_not_stopped_for_confidence(): void
    {
        config()->set('receipt.gate.max_warnings', 0);
        config()->set('receipt.gate.min_confidence', []);

        $this->assertTrue(app(GatePolicy::class)->evaluate([], 0.10, DocumentType::Unknown)->passes);
    }

    #[Test]
    public function a_waived_warning_still_counts_without_a_confidence_to_judge_it_by(): void
    {
        config()->set('receipt.gate.max_warnings', 0);

        $decision = app(GatePolicy::class)->evaluate($this->findings('low_ocr_confidence'), null, DocumentType::Receipt);

        $this->assertFalse($decision->passes);
        $this->assertSame(['low_ocr_confidence'], $decision->warnings);
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
