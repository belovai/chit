<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Ai\ValueObjects\ExtractedLineItem;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\StepOutcome;
use Modules\Pipeline\Services\ArtifactWriter;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\Product\Models\Product;
use Modules\Receipt\Steps\CreateTransactionStep;
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
        config()->set('extraction.ai.fake_documents', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function receiptPayload(array $overrides = []): array
    {
        return [
            'merchant_name' => 'ALDI Hodmezovasarhely',
            'merchant_address' => null,
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
            'ALDI', null, null, 'HUF', 132700, null, 'card',
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

    #[Test]
    public function it_creates_a_location_from_a_reviewed_address(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'create_transaction', 'commit');

        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload([
            'merchant_name' => 'SPAR',
            'merchant_address' => '6723 Szeged, Szilléri sugár út 26.',
        ])]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'SPAR', 'accepted_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', [
            'raw_address' => '6723 Szeged, Szilléri sugár út 26.',
            'accepted_id' => null,
            'accepted_hash_id' => null,
            'candidates' => [],
        ]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            'values' => [
                'merchant_name' => 'SPAR',
                'location_address' => '6723 Szeged, Szilléri sugár út 26.',
            ],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $location = MerchantLocation::query()->firstOrFail();
        $this->assertSame('6723 Szeged, Szilléri sugár út 26.', $location->address);
        $this->assertSame('SPAR', $location->merchant?->name);
        $this->assertSame($location->id, Transaction::query()->firstOrFail()->location_id);
    }

    #[Test]
    public function it_reuses_an_existing_location_instead_of_duplicating_it(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'SPAR']);
        $existing = MerchantLocation::factory()->for($merchant)->create([
            'address' => '6723 Szeged, Szilléri sugár út 26.',
        ]);

        $step = $this->stepRow($run, 'create_transaction', 'commit');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(['merchant_name' => 'SPAR'])]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'SPAR', 'accepted_id' => $merchant->id, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', [
            'raw_address' => null, 'accepted_id' => null, 'accepted_hash_id' => null, 'candidates' => [],
        ]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            // Same branch, spelled the other way — must not create a second row.
            'values' => ['location_address' => '6723 Szeged Szilleri sgt. 26'],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $this->assertSame(1, MerchantLocation::query()->count());
        $this->assertSame($existing->id, Transaction::query()->firstOrFail()->location_id);
    }

    #[Test]
    public function an_explicit_null_location_beats_the_candidate(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'SPAR']);
        $location = MerchantLocation::factory()->for($merchant)->create(['address' => '6723 Szeged, Szilléri sugár út 26.']);

        $step = $this->stepRow($run, 'create_transaction', 'commit');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(['merchant_name' => 'SPAR'])]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'SPAR', 'accepted_id' => $merchant->id, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', [
            'raw_address' => '6723 Szeged, Szilléri sugár út 26.',
            'accepted_id' => $location->id,
            'accepted_hash_id' => $location->hash_id,
            'candidates' => [],
        ]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            'values' => ['location_hash_id' => null],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $this->assertNull(Transaction::query()->firstOrFail()->location_id);
    }

    #[Test]
    public function a_selected_location_hash_id_wins(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'SPAR']);
        $picked = MerchantLocation::factory()->for($merchant)->create(['address' => '1052 Budapest, Deák Ferenc tér 3.']);

        $step = $this->stepRow($run, 'create_transaction', 'commit');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(['merchant_name' => 'SPAR'])]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'SPAR', 'accepted_id' => $merchant->id, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', [
            'raw_address' => null, 'accepted_id' => null, 'accepted_hash_id' => null, 'candidates' => [],
        ]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            'values' => ['location_hash_id' => $picked->hash_id],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $this->assertSame(1, MerchantLocation::query()->count());
        $this->assertSame($picked->id, Transaction::query()->firstOrFail()->location_id);
    }

    #[Test]
    public function a_reviewed_product_pick_wins_over_the_auto_match(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $picked = Product::factory()->for($receipt->owner, 'owner')->create(['name' => 'Falusi tej']);
        $step = $this->stepRow($run, 'create_transaction', 'commit');

        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload()]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'ALDI Hodmezovasarhely', 'accepted_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', ['raw_address' => null, 'accepted_id' => null, 'accepted_hash_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'product_matches', ['items' => [
            ['item_index' => 0, 'description' => 'Tej 2.8%', 'accepted_id' => null, 'candidates' => []],
            ['item_index' => 1, 'description' => 'Kenyer', 'accepted_id' => null, 'candidates' => []],
        ]]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            'values' => [
                'items' => [
                    ['item_index' => 0, 'product_id' => $picked->id],
                ],
            ],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $transaction = Transaction::query()->firstOrFail();
        $tej = $transaction->items()->where('description', 'Tej 2.8%')->firstOrFail();
        $kenyer = $transaction->items()->where('description', 'Kenyer')->firstOrFail();

        $this->assertSame($picked->id, $tej->product_id);
        $this->assertNotNull($kenyer->product_id);
        $this->assertNotSame($picked->id, $kenyer->product_id);
    }

    #[Test]
    public function a_selected_product_hash_id_wins(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $picked = Product::factory()->for($receipt->owner, 'owner')->create(['name' => 'Falusi tej']);
        $foreign = Product::factory()->create(['name' => 'Masik gazda teje']);
        $step = $this->stepRow($run, 'create_transaction', 'commit');

        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload()]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'ALDI Hodmezovasarhely', 'accepted_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', ['raw_address' => null, 'accepted_id' => null, 'accepted_hash_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'product_matches', ['items' => [
            ['item_index' => 0, 'description' => 'Tej 2.8%', 'accepted_id' => null, 'candidates' => []],
            ['item_index' => 1, 'description' => 'Kenyer', 'accepted_id' => null, 'candidates' => []],
        ]]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            'values' => [
                'items' => [
                    ['item_index' => 0, 'product_hash_id' => $picked->hash_id],
                    ['item_index' => 1, 'product_hash_id' => $foreign->hash_id],
                ],
            ],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $transaction = Transaction::query()->firstOrFail();
        $tej = $transaction->items()->where('description', 'Tej 2.8%')->firstOrFail();
        $kenyer = $transaction->items()->where('description', 'Kenyer')->firstOrFail();

        $this->assertSame($picked->id, $tej->product_id);
        // Another owner's row is not theirs to reference — the line falls back
        // to creating their own product from the printed description.
        $this->assertNotSame($foreign->id, $kenyer->product_id);
        $this->assertSame($receipt->owner_id, $kenyer->product?->owner_id);
    }

    #[Test]
    public function a_reviewed_product_name_creates_a_new_product_instead_of_using_the_description(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'create_transaction', 'commit');

        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload()]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'ALDI Hodmezovasarhely', 'accepted_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', ['raw_address' => null, 'accepted_id' => null, 'accepted_hash_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'product_matches', ['items' => [
            ['item_index' => 0, 'description' => 'Tej 2.8%', 'accepted_id' => null, 'candidates' => []],
            ['item_index' => 1, 'description' => 'Kenyer', 'accepted_id' => null, 'candidates' => []],
        ]]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            'values' => [
                'items' => [
                    ['item_index' => 1, 'product_id' => null, 'product_name' => 'Rozsos kenyer'],
                ],
            ],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $kenyer = Transaction::query()->firstOrFail()->items()->where('description', 'Kenyer')->firstOrFail();

        $this->assertNotNull($kenyer->product_id);
        $this->assertSame('Rozsos kenyer', $kenyer->product?->name);
        $this->assertNull(Product::query()->where('name', 'Kenyer')->first());
    }

    #[Test]
    public function a_reviewed_merchant_name_reuses_an_existing_merchant_instead_of_duplicating_it(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $existing = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'OMV']);

        $step = $this->stepRow($run, 'create_transaction', 'commit');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(['merchant_name' => 'ATLANTA KFT'])]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'ATLANTA KFT', 'accepted_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', [
            'raw_address' => null, 'accepted_id' => null, 'accepted_hash_id' => null, 'candidates' => [],
        ]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            // Typed with different casing and padding — still the same shop.
            'values' => ['merchant_name' => '  omv  ', 'location_hash_id' => null],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $this->assertSame(1, Merchant::query()->count());
        $this->assertSame($existing->id, Transaction::query()->firstOrFail()->merchant_id);
    }

    #[Test]
    public function a_reviewed_merchant_name_of_another_owner_still_creates_a_new_merchant(): void
    {
        [$receipt, $run] = $this->receiptRun();
        Merchant::factory()->create(['name' => 'OMV']);

        $step = $this->stepRow($run, 'create_transaction', 'commit');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload(['merchant_name' => 'ATLANTA KFT'])]);
        $this->seedArtifact($step, 'merchant_candidates', ['raw_name' => 'ATLANTA KFT', 'accepted_id' => null, 'candidates' => []]);
        $this->seedArtifact($step, 'location_candidate', [
            'raw_address' => null, 'accepted_id' => null, 'accepted_hash_id' => null, 'candidates' => [],
        ]);
        $this->seedArtifact($step, 'review_decision', [
            'decision' => 'approve',
            'values' => ['merchant_name' => 'OMV', 'location_hash_id' => null],
        ]);

        app(CreateTransactionStep::class)->handle($this->contextFor($step));

        $this->assertSame(2, Merchant::query()->count());
        $this->assertSame(1, Merchant::query()->where('owner_id', $receipt->owner_id)->count());
    }
}
