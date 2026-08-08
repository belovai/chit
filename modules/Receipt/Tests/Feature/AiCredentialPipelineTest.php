<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Ai\Actions\ActivateAiCredential;
use Modules\Ai\Exceptions\NoActiveAiCredentialException;
use Modules\Ai\Models\AiCredential;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Ocr\Testing\FakeOcrEngine;
use Modules\Pipeline\Actions\RetryRun;
use Modules\Pipeline\Enums\RetryMode;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Receipt\Actions\UploadReceipt;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AiCredentialPipelineTest extends TestCase
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

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', (string) file_get_contents(
            base_path('modules/Extraction/Tests/Support/fixtures/aldi-receipt.png'),
        ));
    }

    #[Test]
    public function an_upload_snapshots_the_users_active_credential_on_the_run(): void
    {
        $user = User::factory()->create();
        $credential = AiCredential::factory()->for($user, 'owner')->active()->create();

        $receipt = app(UploadReceipt::class)->handle(
            $user->id,
            $this->image(),
        );

        $run = PipelineRun::query()->findOrFail($receipt->current_run_id);

        $this->assertSame($credential->id, $run->ai_credential_id);
    }

    #[Test]
    public function an_upload_without_a_credential_is_refused_before_any_work_starts(): void
    {
        $user = User::factory()->create();

        $this->expectException(NoActiveAiCredentialException::class);

        try {
            app(UploadReceipt::class)->handle(
                $user->id,
                $this->image(),
            );
        } finally {
            $this->assertSame(0, PipelineRun::query()->count(), 'no run may be queued');
        }
    }

    #[Test]
    public function a_step_receives_the_connection_belonging_to_the_runs_credential(): void
    {
        $ada = User::factory()->create();
        AiCredential::factory()->for($ada, 'owner')->active()->create(['api_key' => 'sk-fake-ada']);

        app(UploadReceipt::class)->handle($ada->id, $this->image());

        $this->assertSame('sk-fake-ada', FakeDocumentAi::lastConnection()?->apiKey);
    }

    #[Test]
    public function swapping_the_active_credential_mid_run_does_not_change_the_running_one(): void
    {
        $user = User::factory()->create();
        $first = AiCredential::factory()->for($user, 'owner')->active()->create(['api_key' => 'sk-fake-first']);

        $receipt = app(UploadReceipt::class)->handle($user->id, $this->image());
        $run = PipelineRun::query()->findOrFail($receipt->current_run_id);

        $second = AiCredential::factory()->for($user, 'owner')->verified()->create(['api_key' => 'sk-fake-second']);
        app(ActivateAiCredential::class)->handle($second);

        $this->assertSame($first->id, $run->fresh()?->ai_credential_id);
    }

    #[Test]
    public function retrying_a_whole_run_carries_its_credential_to_the_new_run(): void
    {
        $user = User::factory()->create();
        $credential = AiCredential::factory()->for($user, 'owner')->active()->create();

        $receipt = app(UploadReceipt::class)->handle($user->id, $this->image());
        $run = PipelineRun::query()->findOrFail($receipt->current_run_id);
        $run->update(['status' => RunStatus::Failed]);

        $retried = app(RetryRun::class)->handle($run, RetryMode::All);

        $this->assertNotSame($run->id, $retried->id);
        $this->assertSame($credential->id, $retried->ai_credential_id);
    }
}
