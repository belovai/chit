<?php

declare(strict_types=1);

namespace Modules\Extraction\Tests\Feature;

use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiUsage;
use Modules\Ai\ValueObjects\ImagePart;
use Modules\Ai\ValueObjects\TextPart;
use Modules\Extraction\Ai\DocumentAi;
use Modules\Extraction\Enums\DocumentType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DocumentAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.fake', true);
        FakeAiProvider::reset();
    }

    private function connection(): AiConnection
    {
        return new AiConnection('fake', 'fake-model', 'sk-fake-key', ['max_tokens' => 8000, 'effort' => 'low']);
    }

    #[Test]
    public function classification_sends_text_only_and_never_an_image(): void
    {
        FakeAiProvider::willRespond([
            'document_type' => 'receipt',
            'confidence' => 0.93,
            'reason' => 'Line items and a total.',
        ]);

        $result = app(DocumentAi::class)->classify($this->connection(), 'SPAR ... 1290 Ft');

        $this->assertSame(DocumentType::Receipt, $result->type);
        $this->assertSame(0.93, $result->confidence);

        $request = FakeAiProvider::calls()[0];

        $this->assertFalse($request->hasImages());
        $this->assertInstanceOf(TextPart::class, $request->content[0]);
        $this->assertNotNull($request->jsonSchema);
        $this->assertTrue($request->cacheSystem, 'the system prompt is identical per document type, so it is cached');
    }

    #[Test]
    public function extraction_sends_the_image_and_the_instruction(): void
    {
        FakeAiProvider::willRespond([
            'merchant_name' => 'SPAR',
            'total' => 1290,
            'currency' => 'HUF',
            'confidence' => 0.91,
            'line_items' => [],
        ]);

        app(DocumentAi::class)->extract($this->connection(), 'raw-png-bytes', 'image/png', DocumentType::Receipt);

        $request = FakeAiProvider::calls()[0];

        $this->assertTrue($request->hasImages());
        $this->assertInstanceOf(ImagePart::class, $request->content[0]);
        $this->assertSame('image/png', $request->content[0]->mimeType);
        $this->assertSame('raw-png-bytes', $request->content[0]->bytes);
        $this->assertInstanceOf(TextPart::class, $request->content[1]);
    }

    #[Test]
    public function usage_from_the_client_reaches_the_result(): void
    {
        FakeAiProvider::willRespond(
            ['document_type' => 'receipt', 'confidence' => 0.9],
            new AiUsage(inputTokens: 800, outputTokens: 40, costUsdMicros: 5_000),
        );

        $result = app(DocumentAi::class)->classify($this->connection(), 'text');

        $this->assertSame(800, $result->usage->inputTokens);
        $this->assertSame(5_000, $result->usage->costUsdMicros);
    }

    #[Test]
    public function an_unclassified_document_cannot_be_extracted(): void
    {
        $this->expectException(AiException::class);
        $this->expectExceptionMessage('Cannot extract from an unclassified document.');

        app(DocumentAi::class)->extract($this->connection(), 'bytes', 'image/png', DocumentType::Unknown);
    }

    /** Carried over from AnthropicDocumentAiTest — the schema shape is domain, not transport. */
    #[Test]
    public function classification_sends_the_classification_schema_and_the_ocr_text(): void
    {
        FakeAiProvider::willRespond(['document_type' => 'utility_bill', 'confidence' => 0.93]);

        $result = app(DocumentAi::class)->classify($this->connection(), "ELMU\nUgyfelszam: 1234567890");

        $this->assertSame(DocumentType::UtilityBill, $result->type);

        $request = FakeAiProvider::calls()[0];

        $this->assertStringContainsString('1234567890', $request->content[0]->text);
        $this->assertSame(
            ['receipt', 'utility_bill', 'unknown'],
            $request->jsonSchema['properties']['document_type']['enum'],
        );
    }

    /** Carried over from AnthropicDocumentAiTest. */
    #[Test]
    public function a_user_hint_is_passed_through_as_a_prior(): void
    {
        FakeAiProvider::willRespond(['document_type' => 'receipt', 'confidence' => 0.9]);

        app(DocumentAi::class)->classify($this->connection(), '...', DocumentType::Receipt);

        $this->assertStringContainsString('receipt', FakeAiProvider::calls()[0]->content[0]->text);
    }

    #[Test]
    public function the_call_carries_the_connection_it_was_given(): void
    {
        FakeAiProvider::willRespond(['document_type' => 'receipt', 'confidence' => 0.9]);

        app(DocumentAi::class)->classify($this->connection(), 'text');

        $this->assertSame('sk-fake-key', FakeAiProvider::connections()[0]->apiKey);
    }
}
