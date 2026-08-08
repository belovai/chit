<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Ai\Models\AiCredential;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Ai\ValueObjects\ExtractedLineItem;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Ocr\Testing\FakeOcrEngine;
use Modules\Pipeline\Actions\StartRun;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Models\ReceiptCorrection;
use Modules\Transaction\Models\Transaction;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReviewReceiptTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Receipt, 2: string} */
    private function parkedReceipt(): array
    {
        Storage::fake('local');
        FakeOcrEngine::reset();
        FakeDocumentAi::reset();
        config()->set('extraction.ocr.engine', 'fake');
        config()->set('extraction.ai.fake_documents', true);
        config()->set('receipt.gate.max_warnings', 0);

        FakeOcrEngine::returns('ALDI ...', 0.94);
        FakeDocumentAi::willClassify(DocumentType::Receipt, 0.97);
        FakeDocumentAi::willExtract(new ExtractedReceipt(
            'ALDI', null, CarbonImmutable::parse('2026-07-30 14:12'), 'HUF', 132700, null, 'card',
            [new ExtractedLineItem('Tej 2.8%', 2.0, 'db', 38900, 77800)],
        ), 0.94);

        $user = User::factory()->create();
        // Must be a real, decodable image: PreprocessImageStep always runs
        // Imagick for real (only OCR/AI have fake test doubles), so a
        // placeholder byte string fails at preprocess_image before the run
        // ever reaches the review gate.
        Storage::disk('local')->put('receipts/a.png', (string) file_get_contents(
            base_path('modules/Extraction/Tests/Support/fixtures/aldi-receipt.png'),
        ));
        $receipt = Receipt::factory()->for($user, 'owner')->create([
            'disk' => 'local', 'path' => 'receipts/a.png', 'mime' => 'image/png',
        ]);
        $credential = AiCredential::factory()->for($user, 'owner')->active()->create();
        $run = app(StartRun::class)->handle('receipt_ingest', $user->id, subject: $receipt, aiCredentialId: $credential->id);
        $receipt->update(['current_run_id' => $run->id]);
        $receipt->refresh();

        return [$user, $receipt, $user->createToken('api')->plainTextToken];
    }

    #[Test]
    public function the_detail_endpoint_exposes_the_review_request(): void
    {
        [$user, $receipt, $token] = $this->parkedReceipt();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/receipts/{$receipt->hash_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_review')
            ->assertJsonPath('data.review_request.doc_type', 'receipt')
            ->assertJsonStructure(['data' => ['extracted', 'candidates', 'review_request' => ['fields', 'findings']]]);
    }

    #[Test]
    public function approving_records_the_corrections_and_creates_the_transaction(): void
    {
        [$user, $receipt, $token] = $this->parkedReceipt();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/receipts/{$receipt->hash_id}/review", [
                'decision' => 'approve',
                'values' => ['merchant_name' => 'ALDI Magyarorszag', 'total_minor' => 132800],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $receipt->refresh();
        $this->assertNotNull($receipt->transaction_id);
        $this->assertSame('1328.00', Transaction::query()->findOrFail($receipt->transaction_id)->total_amount);

        $corrections = ReceiptCorrection::query()->where('receipt_id', $receipt->id)->get();
        $this->assertCount(2, $corrections);
        $this->assertSame(
            ['merchant_name', 'total_minor'],
            $corrections->pluck('field_path')->sort()->values()->all(),
        );
        $this->assertSame(132700, $corrections->firstWhere('field_path', 'total_minor')?->ai_value['value']);
        $this->assertSame(132800, $corrections->firstWhere('field_path', 'total_minor')?->corrected_value['value']);
    }

    #[Test]
    public function approving_without_changes_records_no_corrections(): void
    {
        [$user, $receipt, $token] = $this->parkedReceipt();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/receipts/{$receipt->hash_id}/review", ['decision' => 'approve', 'values' => []])
            ->assertOk();

        $this->assertSame(0, ReceiptCorrection::query()->count());
    }

    #[Test]
    public function rejecting_creates_nothing(): void
    {
        [$user, $receipt, $token] = $this->parkedReceipt();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/receipts/{$receipt->hash_id}/review", ['decision' => 'reject'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertNull($receipt->refresh()->transaction_id);
        $this->assertSame(0, Transaction::query()->count());
    }

    #[Test]
    public function reviewing_a_receipt_that_is_not_parked_is_a_conflict(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user, 'owner')->create(['status' => ReceiptStatus::Approved]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/receipts/{$receipt->hash_id}/review", ['decision' => 'approve'])
            ->assertStatus(409);
    }

    #[Test]
    public function an_unknown_decision_is_rejected(): void
    {
        [$user, $receipt, $token] = $this->parkedReceipt();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/receipts/{$receipt->hash_id}/review", ['decision' => 'maybe'])
            ->assertUnprocessable();
    }

    #[Test]
    public function another_users_receipt_is_hidden(): void
    {
        [$user, $receipt] = $this->parkedReceipt();
        $intruder = User::factory()->create();
        $token = $intruder->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/receipts/{$receipt->hash_id}/review", ['decision' => 'approve'])
            ->assertNotFound();
    }
}
