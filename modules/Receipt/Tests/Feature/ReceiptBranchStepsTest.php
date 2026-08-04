<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Ai\ValueObjects\ExtractedLineItem;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Merchant\Models\Merchant;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\StepOutcome;
use Modules\Pipeline\Services\ArtifactWriter;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\Product\Models\Product;
use Modules\Receipt\Steps\DedupeContentStep;
use Modules\Receipt\Steps\ExtractReceiptStep;
use Modules\Receipt\Steps\MatchMerchantStep;
use Modules\Receipt\Steps\MatchProductsStep;
use Modules\Receipt\Steps\ValidateTotalsStep;
use Modules\Receipt\Tests\Support\MakesReceiptRuns;
use Modules\Transaction\Models\Transaction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReceiptBranchStepsTest extends TestCase
{
    use MakesReceiptRuns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakeDocumentAi::reset();
        config()->set('extraction.ai.provider', 'fake');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function receiptPayload(array $overrides = []): array
    {
        return [
            'merchant_name' => 'ALDI Hodmezovasarhely',
            'occurred_at' => '2026-07-30T14:12:00',
            'currency' => 'HUF',
            'total_minor' => 132700,
            'discount_minor' => null,
            'payment_method' => 'card',
            'items' => [
                ['description' => 'Tej 2.8%', 'quantity' => 2, 'unit' => 'db', 'unit_price_minor' => 38900, 'total_minor' => 77800],
                ['description' => 'Kenyer', 'quantity' => 1, 'unit' => 'db', 'unit_price_minor' => 54900, 'total_minor' => 54900],
            ],
            'confidence' => 0.88,
            ...$overrides,
        ];
    }

    #[Test]
    public function extract_publishes_the_raw_payload_confidence_and_cost(): void
    {
        FakeDocumentAi::willExtract(new ExtractedReceipt(
            'ALDI', null, 'HUF', 132700, null, 'card',
            [new ExtractedLineItem('Tej', 2.0, 'db', 38900, 77800)],
        ), 0.88);

        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'extract_receipt', 'extract');
        $this->putFile('local', 'normalized/x.png', 'png');
        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'normalized_image', ArtifactKind::Binary, null, 'local', 'normalized/x.png', 'image/png', 3,
        ));

        $result = app(ExtractReceiptStep::class)->handle($this->contextFor($step));

        $this->assertSame(StepOutcome::Success, $result->outcome());
        $this->assertSame('extracted_receipt', $result->artifacts()[0]->key);
        $this->assertSame(0.88, $result->confidenceValue());
        $this->assertGreaterThan(0, $result->costUsdMicros());
    }

    #[Test]
    public function match_merchant_accepts_a_clear_winner(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->for($receipt->owner, 'owner')->create(['name' => 'ALDI Hodmezovasarhely']);
        $step = $this->stepRow($run, 'match_merchant', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(), 'confidence' => 0.88]);

        $result = app(MatchMerchantStep::class)->handle($this->contextFor($step));

        $payload = $result->artifacts()[0]->payload;
        $this->assertSame('merchant_candidates', $result->artifacts()[0]->key);
        $this->assertSame($merchant->id, $payload['accepted_id']);
        $this->assertSame([], $result->findings());
    }

    #[Test]
    public function match_merchant_flags_a_name_it_has_never_seen(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'match_merchant', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(), 'confidence' => 0.88]);

        $result = app(MatchMerchantStep::class)->handle($this->contextFor($step));

        $this->assertNull($result->artifacts()[0]->payload['accepted_id']);
        $this->assertSame('new_merchant', $result->findings()[0]->code);
    }

    #[Test]
    public function match_merchant_flags_two_near_equal_candidates_as_ambiguous(): void
    {
        [$receipt, $run] = $this->receiptRun();
        Merchant::factory()->for($receipt->owner, 'owner')->create(['name' => 'ALDI Hodmezovasarhely 1']);
        Merchant::factory()->for($receipt->owner, 'owner')->create(['name' => 'ALDI Hodmezovasarhely 2']);
        $step = $this->stepRow($run, 'match_merchant', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(), 'confidence' => 0.88]);

        $result = app(MatchMerchantStep::class)->handle($this->contextFor($step));

        $codes = array_map(fn ($f) => $f->code, $result->findings());
        $this->assertContains('merchant_ambiguous', $codes);
        $this->assertNull($result->artifacts()[0]->payload['accepted_id']);
    }

    #[Test]
    public function match_products_returns_one_entry_per_line_item(): void
    {
        [$receipt, $run] = $this->receiptRun();
        Product::factory()->for($receipt->owner, 'owner')->create(['name' => 'Tej 2.8%']);
        $step = $this->stepRow($run, 'match_products', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(), 'confidence' => 0.88]);

        $result = app(MatchProductsStep::class)->handle($this->contextFor($step));

        $matches = $result->artifacts()[0]->payload['items'];
        $this->assertCount(2, $matches);
        $this->assertSame(0, $matches[0]['item_index']);
        $this->assertNotNull($matches[0]['accepted_id']);
        $this->assertNull($matches[1]['accepted_id']);
    }

    #[Test]
    public function validate_totals_is_silent_when_the_items_add_up(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'validate_totals', 'validate');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(), 'confidence' => 0.88]);

        $this->assertSame([], app(ValidateTotalsStep::class)->handle($this->contextFor($step))->findings());
    }

    #[Test]
    public function validate_totals_flags_a_sum_mismatch(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'validate_totals', 'validate');
        $this->seedArtifact($step, 'extracted_receipt', [
            'payload' => $this->receiptPayload(['total_minor' => 999900]),
            'confidence' => 0.88,
        ]);

        $findings = app(ValidateTotalsStep::class)->handle($this->contextFor($step))->findings();

        $this->assertSame('line_items_sum_mismatch', $findings[0]->code);
        $this->assertSame(867200, $findings[0]->context['delta_minor']);
    }

    #[Test]
    public function validate_totals_subtracts_the_discount_before_comparing_to_the_total(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'validate_totals', 'validate');
        $this->seedArtifact($step, 'extracted_receipt', [
            'payload' => $this->receiptPayload([
                'total_minor' => 122700,
                'discount_minor' => 10000,
            ]),
            'confidence' => 0.88,
        ]);

        $this->assertSame([], app(ValidateTotalsStep::class)->handle($this->contextFor($step))->findings());
    }

    #[Test]
    public function validate_totals_tolerates_small_rounding_differences(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'validate_totals', 'validate');
        $this->seedArtifact($step, 'extracted_receipt', [
            'payload' => $this->receiptPayload(['total_minor' => 132800]),
            'confidence' => 0.88,
        ]);

        $this->assertSame([], app(ValidateTotalsStep::class)->handle($this->contextFor($step))->findings());
    }

    #[Test]
    public function validate_totals_flags_a_missing_total_and_a_future_date(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'validate_totals', 'validate');
        $this->seedArtifact($step, 'extracted_receipt', [
            'payload' => $this->receiptPayload([
                'total_minor' => null,
                'occurred_at' => now()->addWeek()->toIso8601String(),
            ]),
            'confidence' => 0.88,
        ]);

        $codes = array_map(
            fn ($f) => $f->code,
            app(ValidateTotalsStep::class)->handle($this->contextFor($step))->findings(),
        );

        $this->assertContains('total_missing', $codes);
        $this->assertContains('date_in_future', $codes);
    }

    #[Test]
    public function dedupe_content_flags_an_existing_transaction_with_the_same_merchant_day_and_total(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->for($receipt->owner, 'owner')->create(['name' => 'ALDI Hodmezovasarhely']);
        Transaction::factory()->for($receipt->owner, 'owner')->for($merchant)->create([
            'total_amount' => 1327.00,
            'occurred_at' => '2026-07-30 09:00:00',
            'currency' => 'HUF',
        ]);
        $step = $this->stepRow($run, 'dedupe_content', 'validate');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(), 'confidence' => 0.88]);
        $this->seedArtifact($step, 'merchant_candidates', ['accepted_id' => $merchant->id, 'candidates' => []]);

        $findings = app(DedupeContentStep::class)->handle($this->contextFor($step))->findings();

        $this->assertSame('possible_duplicate', $findings[0]->code);
    }

    #[Test]
    public function dedupe_content_is_silent_without_an_accepted_merchant(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'dedupe_content', 'validate');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(), 'confidence' => 0.88]);
        $this->seedArtifact($step, 'merchant_candidates', ['accepted_id' => null, 'candidates' => []]);

        $this->assertSame([], app(DedupeContentStep::class)->handle($this->contextFor($step))->findings());
    }
}
