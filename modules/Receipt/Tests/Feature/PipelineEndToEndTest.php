<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Ai\Models\AiCredential;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Ai\ValueObjects\ExtractedLineItem;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Ocr\Testing\FakeOcrEngine;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Pipeline\Actions\ResumeRun;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\Receipt\Actions\ReviewReceipt;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\Transaction\Models\Transaction;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PipelineEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        FakeOcrEngine::reset();
        FakeDocumentAi::reset();
        config()->set('extraction.ocr.engine', 'fake');
        config()->set('extraction.ai.fake_documents', true);
        config()->set('receipt.gate.max_warnings', 0);
    }

    private function receiptFor(User $user): Receipt
    {
        // A fresh hash per call: two receipts from the same user must not
        // collide on dedupe_file_hash just because both tests reuse the same
        // fixture image.
        $suffix = (string) Str::uuid();
        $path = "receipts/{$suffix}.png";

        Storage::disk('local')->put($path, (string) file_get_contents(
            base_path('modules/Extraction/Tests/Support/fixtures/aldi-receipt.png'),
        ));

        return Receipt::factory()->for($user, 'owner')->create([
            'disk' => 'local', 'path' => $path, 'mime' => 'image/png',
            'file_hash' => $suffix, 'status' => ReceiptStatus::Pending,
        ]);
    }

    private function uploadAndRun(User $user): Receipt
    {
        $receipt = $this->receiptFor($user);
        $credential = AiCredential::query()->forUser($user->id)->active()->first()
            ?? AiCredential::factory()->for($user, 'owner')->active()->create();

        app(StartRun::class)->handle('receipt_ingest', $user->id, subject: $receipt, aiCredentialId: $credential->id);

        return $receipt->refresh();
    }

    private function primeReceiptExtraction(): void
    {
        FakeOcrEngine::returns("ALDI\nTej 2 db 389\nOSSZESEN 1327", 0.94);
        FakeDocumentAi::willClassify(DocumentType::Receipt, 0.97);
        FakeDocumentAi::willExtract(new ExtractedReceipt(
            merchantName: 'ALDI',
            merchantAddress: '6723 Szeged, Szilléri sugár út 26.',
            occurredAt: CarbonImmutable::parse('2026-07-30 14:12'),
            currency: 'HUF',
            totalMinor: 132700,
            discountMinor: null,
            paymentMethod: 'card',
            items: [new ExtractedLineItem('Tej 2.8%', 2.0, 'db', 38900, 77800),
                new ExtractedLineItem('Kenyer', 1.0, 'db', 54900, 54900)],
        ), 0.94);
    }

    #[Test]
    public function a_clean_receipt_from_a_known_merchant_commits_without_a_human(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'ALDI']);
        // The branch must already be on file too, or the new location-matching
        // gate has something genuinely new to ask about.
        MerchantLocation::factory()->for($merchant)->create(['address' => '6723 Szeged, Szilléri sugár út 26.']);
        $this->primeReceiptExtraction();

        $receipt = $this->uploadAndRun($user);

        $this->assertSame(RunStatus::Succeeded, $receipt->currentRun?->status);
        $this->assertSame(ReceiptStatus::Approved, $receipt->status);
        $this->assertNotNull($receipt->transaction_id);

        $transaction = Transaction::query()->findOrFail($receipt->transaction_id);
        $this->assertSame('1327.00', $transaction->total_amount);
        $this->assertCount(2, $transaction->items);
        $this->assertSame('receipt', $transaction->source->value);
    }

    #[Test]
    public function an_unknown_merchant_parks_the_run_for_review(): void
    {
        $user = User::factory()->create();
        $this->primeReceiptExtraction();

        $receipt = $this->uploadAndRun($user);
        $run = $receipt->currentRun;

        $this->assertSame(RunStatus::AwaitingManual, $run?->status);
        $this->assertSame(ReceiptStatus::NeedsReview, $receipt->status);
        $this->assertNull($receipt->transaction_id);

        $request = $run->artifacts()->where('key', 'review_request')->whereNull('superseded_at')->firstOrFail();
        $this->assertContains('new_merchant', $request->payload['warnings']);
        $this->assertContains('merchant', $request->payload['fields']);
    }

    #[Test]
    public function approving_a_parked_run_creates_the_merchant_and_the_transaction(): void
    {
        $user = User::factory()->create();
        $this->primeReceiptExtraction();
        $receipt = $this->uploadAndRun($user);
        $run = $receipt->currentRun;

        app(ResumeRun::class)->approve($run, [
            new PendingArtifact('review_decision', ArtifactKind::Json, [
                'decision' => 'approve',
                'values' => ['merchant_name' => 'ALDI Magyarorszag'],
            ]),
        ]);

        $run->refresh();
        $receipt->refresh();

        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame(ReceiptStatus::Approved, $receipt->status);
        $this->assertNotNull($receipt->transaction_id);
        $this->assertSame(
            'ALDI Magyarorszag',
            Merchant::query()->where('owner_id', $user->id)->firstOrFail()->name,
        );
    }

    #[Test]
    public function a_discount_corrected_during_review_is_what_the_transaction_records(): void
    {
        $user = User::factory()->create();
        FakeOcrEngine::returns("ALDI\nTej 2 db 389\nOSSZESEN 1127", 0.94);
        FakeDocumentAi::willClassify(DocumentType::Receipt, 0.97);
        FakeDocumentAi::willExtract(new ExtractedReceipt(
            merchantName: 'ALDI',
            merchantAddress: null,
            occurredAt: CarbonImmutable::parse('2026-07-30 14:12'),
            currency: 'HUF',
            totalMinor: 112700,
            // One deduction too many was read off the picture — the reviewer
            // corrects the discount, not the printed total.
            discountMinor: 20000,
            paymentMethod: 'card',
            items: [new ExtractedLineItem('Tej 2.8%', 2.0, 'db', 38900, 77800),
                new ExtractedLineItem('Kenyer', 1.0, 'db', 54900, 54900)],
        ), 0.94);

        $receipt = $this->uploadAndRun($user);
        $run = $receipt->currentRun;

        app(ResumeRun::class)->approve($run, [
            new PendingArtifact('review_decision', ArtifactKind::Json, [
                'decision' => 'approve',
                'values' => ['discount_minor' => 18600],
            ]),
        ]);

        $transaction = Transaction::query()->findOrFail($receipt->refresh()->transaction_id);

        $this->assertSame('186.00', $transaction->discount_amount);
    }

    #[Test]
    public function a_discount_cleared_during_review_is_not_recorded_at_all(): void
    {
        $user = User::factory()->create();
        FakeOcrEngine::returns("ALDI\nTej 2 db 389\nOSSZESEN 1127", 0.94);
        FakeDocumentAi::willClassify(DocumentType::Receipt, 0.97);
        FakeDocumentAi::willExtract(new ExtractedReceipt(
            merchantName: 'ALDI',
            merchantAddress: null,
            occurredAt: CarbonImmutable::parse('2026-07-30 14:12'),
            currency: 'HUF',
            totalMinor: 112700,
            discountMinor: 20000,
            paymentMethod: 'card',
            items: [new ExtractedLineItem('Tej 2.8%', 2.0, 'db', 38900, 77800),
                new ExtractedLineItem('Kenyer', 1.0, 'db', 54900, 54900)],
        ), 0.94);

        $receipt = $this->uploadAndRun($user);
        $run = $receipt->currentRun;

        app(ResumeRun::class)->approve($run, [
            new PendingArtifact('review_decision', ArtifactKind::Json, [
                'decision' => 'approve',
                'values' => ['discount_minor' => null],
            ]),
        ]);

        $transaction = Transaction::query()->findOrFail($receipt->refresh()->transaction_id);

        $this->assertNull($transaction->discount_amount);
    }

    #[Test]
    public function rejecting_a_parked_run_creates_nothing(): void
    {
        $user = User::factory()->create();
        $this->primeReceiptExtraction();
        $receipt = $this->uploadAndRun($user);
        $run = $receipt->currentRun;

        app(ResumeRun::class)->reject($run, [
            new PendingArtifact('review_decision', ArtifactKind::Json, ['decision' => 'reject']),
        ]);

        $this->assertSame(RunStatus::Canceled, $run->refresh()->status);
        $this->assertSame(ReceiptStatus::Rejected, $receipt->refresh()->status);
        $this->assertNull($receipt->refresh()->transaction_id);
        $this->assertSame(0, Transaction::query()->count());
    }

    #[Test]
    public function a_sum_mismatch_parks_the_run_even_with_a_known_merchant(): void
    {
        $user = User::factory()->create();
        Merchant::factory()->for($user, 'owner')->create(['name' => 'ALDI']);
        FakeOcrEngine::returns('ALDI ...', 0.94);
        FakeDocumentAi::willClassify(DocumentType::Receipt, 0.97);
        FakeDocumentAi::willExtract(new ExtractedReceipt(
            'ALDI', null, CarbonImmutable::parse('2026-07-30'), 'HUF', 999900, null, 'card',
            [new ExtractedLineItem('Tej', 2.0, 'db', 38900, 77800)],
        ), 0.94);

        $receipt = $this->uploadAndRun($user);
        $run = $receipt->currentRun;

        $this->assertSame(RunStatus::AwaitingManual, $run?->status);
        $request = $run->artifacts()->where('key', 'review_request')->whereNull('superseded_at')->firstOrFail();
        $this->assertContains('line_items_sum_mismatch', $request->payload['blockers']);
    }

    #[Test]
    public function a_missing_file_fails_the_run(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user, 'owner')->create([
            'disk' => 'local', 'path' => 'receipts/gone.png',
        ]);

        $run = app(StartRun::class)->handle('receipt_ingest', $user->id, subject: $receipt)->refresh();

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertSame(ReceiptStatus::Failed, $receipt->refresh()->status);
        $this->assertSame('store_file', $run->error_summary['step_key']);
    }

    #[Test]
    public function the_second_receipt_from_a_known_branch_needs_no_review(): void
    {
        $user = User::factory()->create();

        FakeDocumentAi::willExtract(new ExtractedReceipt(
            merchantName: 'SPAR',
            merchantAddress: '6723 Szeged, Szilléri sugár út 26.',
            occurredAt: CarbonImmutable::parse('2026-07-30 10:35:00'),
            currency: 'HUF',
            totalMinor: 190000,
            discountMinor: null,
            paymentMethod: 'card',
            items: [],
        ));

        // First run: brand new merchant and brand new branch, so it parks.
        $first = $this->uploadAndRun($user);
        $this->assertSame('needs_review', $first->refresh()->status->value);

        app(ReviewReceipt::class)->approve($first->refresh(), [
            'merchant_name' => 'SPAR',
            'location_address' => '6723 Szeged, Szilléri sugár út 26.',
        ]);

        $this->assertSame(1, Merchant::query()->count());
        $this->assertSame(1, MerchantLocation::query()->count());

        // Second run: same shop, same branch, but a different purchase — same
        // date/total as the first would be dedupe_content's job to catch, not
        // this test's.
        FakeDocumentAi::willExtract(new ExtractedReceipt(
            merchantName: 'SPAR',
            merchantAddress: '6723 Szeged, Szilléri sugár út 26.',
            occurredAt: CarbonImmutable::parse('2026-07-31 09:10:00'),
            currency: 'HUF',
            totalMinor: 250000,
            discountMinor: null,
            paymentMethod: 'card',
            items: [],
        ));

        $second = $this->uploadAndRun($user);

        $this->assertSame('approved', $second->refresh()->status->value);
        $this->assertSame(1, Merchant::query()->count());
        $this->assertSame(1, MerchantLocation::query()->count());
        $this->assertNotNull($second->refresh()->transaction_id);
    }

    #[Test]
    public function approving_without_typing_an_address_still_records_the_extracted_branch(): void
    {
        $user = User::factory()->create();

        FakeDocumentAi::willExtract(new ExtractedReceipt(
            merchantName: 'OMV',
            merchantAddress: '6800 Hódmezővásárhely, Kutasi út 17.',
            occurredAt: CarbonImmutable::parse('2026-07-14 17:14:00'),
            currency: 'HUF',
            totalMinor: 3759200,
            discountMinor: null,
            paymentMethod: 'card',
            items: [],
        ));

        $receipt = $this->uploadAndRun($user);
        $this->assertSame('needs_review', $receipt->refresh()->status->value);

        // The reviewer changes nothing — the extracted address is all we have,
        // and it must still become the transaction's branch.
        app(ReviewReceipt::class)->approve($receipt->refresh(), []);

        $location = MerchantLocation::query()->sole();

        $this->assertSame('6800 Hódmezővásárhely, Kutasi út 17.', $location->address);
        $this->assertSame(Merchant::query()->sole()->id, $location->merchant_id);
        $this->assertSame($location->id, $receipt->refresh()->transaction?->location_id);
    }

    #[Test]
    public function reassigning_the_merchant_by_hand_records_no_branch_from_the_picture(): void
    {
        $user = User::factory()->create();
        $picked = Merchant::factory()->for($user, 'owner')->create(['name' => 'MOL']);

        FakeDocumentAi::willExtract(new ExtractedReceipt(
            merchantName: 'OMV',
            merchantAddress: '6800 Hódmezővásárhely, Kutasi út 17.',
            occurredAt: CarbonImmutable::parse('2026-07-14 17:14:00'),
            currency: 'HUF',
            totalMinor: 3759200,
            discountMinor: null,
            paymentMethod: 'card',
            items: [],
        ));

        $receipt = $this->uploadAndRun($user);

        // The reviewer says this is MOL, not the OMV printed on it — so OMV's
        // address must not become a MOL branch behind their back.
        app(ReviewReceipt::class)->approve($receipt->refresh(), ['merchant_id' => $picked->id]);

        $transaction = $receipt->refresh()->transaction;

        $this->assertSame($picked->id, $transaction?->merchant_id);
        $this->assertNull($transaction?->location_id);
        $this->assertSame(0, MerchantLocation::query()->count());
    }

    #[Test]
    public function an_address_that_normalizes_to_nothing_records_no_branch(): void
    {
        $user = User::factory()->create();

        FakeDocumentAi::willExtract(new ExtractedReceipt(
            merchantName: 'OMV',
            merchantAddress: '- - -',
            occurredAt: CarbonImmutable::parse('2026-07-14 17:14:00'),
            currency: 'HUF',
            totalMinor: 3759200,
            discountMinor: null,
            paymentMethod: 'card',
            items: [],
        ));

        $receipt = $this->uploadAndRun($user);
        app(ReviewReceipt::class)->approve($receipt->refresh(), []);

        // A row with no comparison key could never match again, so every later
        // receipt from this shop would add another one.
        $this->assertSame(0, MerchantLocation::query()->count());
        $this->assertNull($receipt->refresh()->transaction?->location_id);
    }

    #[Test]
    public function the_document_type_picked_during_review_branches_the_run(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'ALDI']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '6723 Szeged, Szilléri sugár út 26.']);
        $this->primeReceiptExtraction();
        FakeDocumentAi::willClassify(DocumentType::Unknown, 0.30);

        $receipt = $this->uploadAndRun($user);

        $this->assertSame(RunStatus::AwaitingManual, $receipt->currentRun?->status);
        $this->assertSame([], $receipt->currentRun->currentSteps()
            ->where('stage', 'extract')->pluck('step_key')->all());

        app(ReviewReceipt::class)->approve($receipt, ['doc_type' => 'receipt']);

        $receipt->refresh();

        $this->assertSame(DocumentType::Receipt, $receipt->doc_type);
        $this->assertSame(RunStatus::Succeeded, $receipt->currentRun?->status);
        $this->assertNotNull($receipt->transaction_id);
        $this->assertSame('1327.00', Transaction::query()->findOrFail($receipt->transaction_id)->total_amount);
    }
}
