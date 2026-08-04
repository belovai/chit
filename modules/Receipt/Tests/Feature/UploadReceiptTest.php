<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Ocr\Testing\FakeOcrEngine;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Receipt\Models\Receipt;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UploadReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        FakeOcrEngine::reset();
        FakeDocumentAi::reset();
        FakeOcrEngine::returns('ALDI ...', 0.9);
        FakeDocumentAi::willClassify(DocumentType::Receipt, 0.95);
        config()->set('extraction.ocr.engine', 'fake');
        config()->set('extraction.ai.provider', 'fake');
        config()->set('receipt.upload.disk', 'local');
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', (string) file_get_contents(
            base_path('modules/Extraction/Tests/Support/fixtures/aldi-receipt.png'),
        ));
    }

    #[Test]
    public function it_stores_the_file_creates_the_receipt_and_starts_a_run(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/receipts', ['file' => $this->image()]);

        $response->assertStatus(202);

        $receipt = Receipt::query()->firstOrFail();
        $this->assertSame($user->id, $receipt->owner_id);
        $this->assertSame('receipt.png', $receipt->original_filename);
        Storage::disk('local')->assertExists($receipt->path);
        $this->assertSame(64, mb_strlen($receipt->file_hash));
        $this->assertNotNull($receipt->current_run_id);
        $this->assertSame('receipt_ingest', PipelineRun::query()->findOrFail($receipt->current_run_id)->definition_key);
        $response->assertJsonPath('data.hash_id', $receipt->hash_id);
    }

    #[Test]
    public function the_stored_hash_is_the_sha256_of_the_uploaded_bytes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;
        $bytes = (string) file_get_contents(base_path('modules/Extraction/Tests/Support/fixtures/aldi-receipt.png'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/receipts', ['file' => $this->image()]);

        $this->assertSame(hash('sha256', $bytes), Receipt::query()->firstOrFail()->file_hash);
    }

    #[Test]
    public function it_records_a_document_type_hint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/receipts', ['file' => $this->image(), 'doc_type_hint' => 'utility_bill']);

        $this->assertSame(DocumentType::UtilityBill, Receipt::query()->firstOrFail()->doc_type_hint);
    }

    #[Test]
    public function it_rejects_an_unsupported_file_type(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/receipts', ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    #[Test]
    public function it_rejects_an_unknown_hint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/receipts', ['file' => $this->image(), 'doc_type_hint' => 'banana'])
            ->assertUnprocessable();
    }

    #[Test]
    public function it_lists_only_the_authenticated_users_receipts(): void
    {
        $user = User::factory()->create();
        Receipt::factory()->for($user, 'owner')->create(['original_filename' => 'mine.png']);
        Receipt::factory()->create(['original_filename' => 'theirs.png']);
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_filename', 'mine.png');
    }

    #[Test]
    public function upload_requires_authentication(): void
    {
        $this->post('/api/receipts', ['file' => $this->image()])->assertUnauthorized();
    }
}
