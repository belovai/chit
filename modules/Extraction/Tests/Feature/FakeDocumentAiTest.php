<?php

declare(strict_types=1);

namespace Modules\Extraction\Tests\Feature;

use Modules\Ai\Exceptions\AiException;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Extraction\Ai\Contracts\DocumentClassifier;
use Modules\Extraction\Ai\Contracts\DocumentExtractor;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Ai\ValueObjects\ExtractedBill;
use Modules\Extraction\Enums\DocumentType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FakeDocumentAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeDocumentAi::reset();
        config()->set('extraction.ai.fake_documents', true);
    }

    private function connection(): AiConnection
    {
        return new AiConnection('fake', 'fake-model', 'sk-fake-key');
    }

    #[Test]
    public function the_container_resolves_the_fake_when_configured(): void
    {
        $this->assertInstanceOf(FakeDocumentAi::class, app(DocumentClassifier::class));
        $this->assertInstanceOf(FakeDocumentAi::class, app(DocumentExtractor::class));
    }

    #[Test]
    public function it_returns_the_primed_classification(): void
    {
        FakeDocumentAi::willClassify(DocumentType::UtilityBill, 0.71);

        $result = app(DocumentClassifier::class)->classify($this->connection(), 'ELMU ...');

        $this->assertSame(DocumentType::UtilityBill, $result->type);
        $this->assertSame(0.71, $result->confidence);
        $this->assertSame(1, FakeDocumentAi::classifyCount());
        $this->assertSame('ELMU ...', FakeDocumentAi::lastOcrText());
    }

    #[Test]
    public function it_returns_the_primed_document(): void
    {
        $bill = new ExtractedBill('ELMU', '1234567890', 'HUF', 1845000, null, null, null, 45231.0, null, 312.0, 'kWh');
        FakeDocumentAi::willExtract($bill, 0.88);

        $result = app(DocumentExtractor::class)->extract($this->connection(), 'fake-bytes', 'image/png', DocumentType::UtilityBill);

        $this->assertSame($bill, $result->document);
        $this->assertSame(0.88, $result->confidence);
        $this->assertGreaterThan(0, $result->usage->costUsdMicros);
        $this->assertSame('fake-bytes', FakeDocumentAi::lastImageBytes());
        $this->assertSame('image/png', FakeDocumentAi::lastImageMimeType());
    }

    #[Test]
    public function a_primed_failure_is_thrown_once(): void
    {
        FakeDocumentAi::willFail(AiException::retryable('simulated 429'));

        try {
            app(DocumentClassifier::class)->classify($this->connection(), 'anything');
            $this->fail('expected an AiException');
        } catch (AiException $exception) {
            $this->assertTrue($exception->isRetryable());
        }

        // The second call succeeds — priming is one-shot.
        $this->assertSame(
            DocumentType::Receipt,
            app(DocumentClassifier::class)->classify($this->connection(), 'anything')->type,
        );
    }
}
