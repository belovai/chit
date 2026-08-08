<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Models\AiUsageLog;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Ocr\Testing\FakeOcrEngine;
use Modules\Pipeline\Jobs\AdvanceRun;
use Modules\Receipt\Actions\UploadReceipt;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CredentialIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.fake', true);
        config()->set('extraction.ai.fake_documents', true);
        config()->set('extraction.ocr.engine', 'fake');
        config()->set('receipt.upload.disk', 'local');
        Storage::fake(config('receipt.upload.disk'));
        FakeOcrEngine::reset();
        FakeOcrEngine::returns('ALDI ...', 0.9);
        FakeDocumentAi::reset();
        FakeDocumentAi::willClassify(DocumentType::Receipt, 0.95);
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents(
            base_path('modules/Extraction/Tests/Support/fixtures/aldi-receipt.png'),
        ));
    }

    #[Test]
    public function two_uploads_processed_in_sequence_use_two_different_keys(): void
    {
        $ada = User::factory()->create();
        $grace = User::factory()->create();

        AiCredential::factory()->for($ada, 'owner')->active()->create(['api_key' => 'sk-fake-ada']);
        AiCredential::factory()->for($grace, 'owner')->active()->create(['api_key' => 'sk-fake-grace']);

        app(UploadReceipt::class)->handle($ada->id, $this->image('a.jpg'));
        $this->assertSame('sk-fake-ada', FakeDocumentAi::lastConnection()?->apiKey);

        app(UploadReceipt::class)->handle($grace->id, $this->image('b.jpg'));
        $this->assertSame('sk-fake-grace', FakeDocumentAi::lastConnection()?->apiKey);
    }

    #[Test]
    public function usage_is_attributed_to_the_user_who_paid_for_it(): void
    {
        // The real DocumentAi path (not FakeDocumentAi) is what actually
        // records usage — it is the one that goes through AiClientFactory.
        config()->set('extraction.ai.fake_documents', false);
        FakeAiProvider::reset();
        FakeAiProvider::willRespond([
            'document_type' => 'receipt',
            'confidence' => 0.95,
            'merchant_name' => 'ALDI',
            'items' => [],
        ]);

        $ada = User::factory()->create();
        $credential = AiCredential::factory()->for($ada, 'owner')->active()->create(['provider' => 'fake', 'model' => 'fake-model']);

        app(UploadReceipt::class)->handle($ada->id, $this->image('a.jpg'));

        foreach (AiUsageLog::query()->get() as $log) {
            $this->assertSame($ada->id, $log->owner_id);
            $this->assertSame($credential->id, $log->ai_credential_id);
        }
    }

    #[Test]
    public function a_queued_job_payload_never_contains_key_material(): void
    {
        $user = User::factory()->create();
        AiCredential::factory()->for($user, 'owner')->active()->create(['api_key' => 'sk-fake-supersecret']);

        $payload = serialize(new AdvanceRun(1));

        $this->assertStringNotContainsString('sk-fake-supersecret', $payload);
        $this->assertStringNotContainsString('api_key', $payload);
    }

    #[Test]
    public function no_queued_job_class_holds_an_ai_connection(): void
    {
        // A connection carries a plaintext key. If one is ever stored on a job
        // property, Horizon and failed_jobs will show it.
        $jobFiles = glob(base_path('modules/*/Jobs/*.php')) ?: [];

        $this->assertNotEmpty($jobFiles);

        foreach ($jobFiles as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString(
                'AiConnection',
                $source,
                basename($file).' holds an AiConnection; resolve it inside handle() instead.',
            );
        }
    }
}
