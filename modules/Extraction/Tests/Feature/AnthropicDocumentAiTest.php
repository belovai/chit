<?php

declare(strict_types=1);

namespace Modules\Extraction\Tests\Feature;

use Modules\Extraction\Ai\Support\CostCalculator;
use Modules\Extraction\Ai\ValueObjects\ExtractedBill;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Exceptions\AiException;
use Modules\Extraction\Tests\Support\RecordingAnthropicDocumentAi;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnthropicDocumentAiTest extends TestCase
{
    private function provider(): RecordingAnthropicDocumentAi
    {
        return new RecordingAnthropicDocumentAi(app(CostCalculator::class));
    }

    #[Test]
    public function classification_sends_the_classification_schema_and_the_ocr_text(): void
    {
        $provider = $this->provider();
        $provider->nextPayload = ['document_type' => 'utility_bill', 'confidence' => 0.93, 'reason' => 'has a billing period'];

        $result = $provider->classify("ELMU\nUgyfelszam: 1234567890");

        $this->assertSame(DocumentType::UtilityBill, $result->type);
        $this->assertSame(0.93, $result->confidence);
        $this->assertStringContainsString('1234567890', (string) $provider->lastRequest['user']);
        $this->assertSame(
            ['receipt', 'utility_bill', 'unknown'],
            $provider->lastRequest['schema']['properties']['document_type']['enum'],
        );
    }

    #[Test]
    public function a_user_hint_is_passed_through_as_a_prior(): void
    {
        $provider = $this->provider();
        $provider->nextPayload = ['document_type' => 'receipt', 'confidence' => 0.9];

        $provider->classify('...', DocumentType::Receipt);

        $this->assertStringContainsString('receipt', (string) $provider->lastRequest['user']);
    }

    #[Test]
    public function an_unrecognised_document_type_falls_back_to_unknown(): void
    {
        $provider = $this->provider();
        $provider->nextPayload = ['document_type' => 'invoice_for_a_spaceship', 'confidence' => 0.4];

        $this->assertSame(DocumentType::Unknown, $provider->classify('...')->type);
    }

    #[Test]
    public function receipt_extraction_maps_the_payload_and_reports_usage(): void
    {
        $provider = $this->provider();
        $provider->nextPayload = [
            'merchant_name' => 'ALDI',
            'occurred_at' => '2026-07-30T14:12:00',
            'currency' => 'HUF',
            'total_minor' => 132700,
            'discount_minor' => null,
            'payment_method' => 'card',
            'items' => [['description' => 'Tej', 'quantity' => 2, 'unit' => 'db', 'unit_price_minor' => 38900, 'total_minor' => 77800]],
            'confidence' => 0.84,
        ];
        $imageBytes = (string) file_get_contents(base_path('modules/Extraction/Tests/Support/fixtures/aldi-receipt.png'));

        $result = $provider->extract($imageBytes, 'image/png', DocumentType::Receipt);

        $this->assertInstanceOf(ExtractedReceipt::class, $result->document);
        $this->assertSame('ALDI', $result->document->merchantName);
        $this->assertSame(0.84, $result->confidence);
        $this->assertSame(4000, $result->usage->inputTokens);
        $this->assertSame(30000, $result->usage->costUsdMicros);
        $this->assertSame($provider->nextPayload, $result->rawResponse);
        $this->assertIsArray($provider->lastRequest['user']);
        $this->assertSame('image', $provider->lastRequest['user'][0]['type']);
        $this->assertSame('image/png', $provider->lastRequest['user'][0]['source']['mediaType']);
        $this->assertSame(base64_encode($imageBytes), $provider->lastRequest['user'][0]['source']['data']);
        $this->assertSame('text', $provider->lastRequest['user'][1]['type']);
    }

    #[Test]
    public function bill_extraction_uses_the_bill_schema_and_prompt(): void
    {
        $provider = $this->provider();
        $provider->nextPayload = [
            'provider_name' => 'ELMU',
            'customer_reference' => '1234567890',
            'currency' => 'HUF',
            'total_minor' => 1845000,
            'meter_reading' => 45231,
            'confidence' => 0.9,
        ];
        $imageBytes = (string) file_get_contents(base_path('modules/Extraction/Tests/Support/fixtures/elmu-bill.png'));

        $result = $provider->extract($imageBytes, 'image/png', DocumentType::UtilityBill);

        $this->assertInstanceOf(ExtractedBill::class, $result->document);
        $this->assertSame('1234567890', $result->document->customerReference);
        $this->assertArrayHasKey('customer_reference', $provider->lastRequest['schema']['properties']);
        $this->assertStringContainsString('ugyfelszam', mb_strtolower((string) $provider->lastRequest['system']));
    }

    #[Test]
    public function extracting_an_unknown_document_type_is_a_permanent_failure(): void
    {
        $provider = $this->provider();

        try {
            $provider->extract('bytes', 'image/png', DocumentType::Unknown);
            $this->fail('expected an AiException');
        } catch (AiException $exception) {
            $this->assertFalse($exception->isRetryable());
        }
    }

    #[Test]
    public function a_transport_failure_propagates_with_its_retryability(): void
    {
        $provider = $this->provider();
        $provider->nextFailure = AiException::retryable('429');

        try {
            $provider->classify('...');
            $this->fail('expected an AiException');
        } catch (AiException $exception) {
            $this->assertTrue($exception->isRetryable());
        }
    }
}
