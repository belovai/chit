<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Testing;

use Modules\Ai\Exceptions\AiException;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiUsage;
use Modules\Extraction\Ai\Contracts\DocumentClassifier;
use Modules\Extraction\Ai\Contracts\DocumentExtractor;
use Modules\Extraction\Ai\ValueObjects\ClassificationResult;
use Modules\Extraction\Ai\ValueObjects\ExtractedBill;
use Modules\Extraction\Ai\ValueObjects\ExtractedLineItem;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Extraction\Ai\ValueObjects\ExtractionResult;
use Modules\Extraction\Enums\DocumentType;

/**
 * Ships in the module rather than in Tests/ so the Receipt module (and anything
 * else downstream) can bind it without depending on this module's test autoload.
 */
final class FakeDocumentAi implements DocumentClassifier, DocumentExtractor
{
    private static ?DocumentType $classifyType = null;

    private static float $classifyConfidence = 0.95;

    private static ExtractedReceipt|ExtractedBill|null $document = null;

    private static float $extractConfidence = 0.9;

    private static ?AiException $failure = null;

    private static int $classifyCount = 0;

    private static int $extractCount = 0;

    private static ?string $lastOcrText = null;

    private static ?string $lastImageBytes = null;

    private static ?string $lastImageMimeType = null;

    private static ?AiConnection $lastConnection = null;

    public static function lastConnection(): ?AiConnection
    {
        return self::$lastConnection;
    }

    public static function willClassify(DocumentType $type, float $confidence = 0.95): void
    {
        self::$classifyType = $type;
        self::$classifyConfidence = $confidence;
    }

    public static function willExtract(ExtractedReceipt|ExtractedBill $document, float $confidence = 0.9): void
    {
        self::$document = $document;
        self::$extractConfidence = $confidence;
    }

    /** Next call of either method throws this. Cleared by reset(). */
    public static function willFail(AiException $exception): void
    {
        self::$failure = $exception;
    }

    public static function reset(): void
    {
        self::$classifyType = null;
        self::$classifyConfidence = 0.95;
        self::$document = null;
        self::$extractConfidence = 0.9;
        self::$failure = null;
        self::$classifyCount = 0;
        self::$extractCount = 0;
        self::$lastOcrText = null;
        self::$lastImageBytes = null;
        self::$lastImageMimeType = null;
        self::$lastConnection = null;
    }

    public static function classifyCount(): int
    {
        return self::$classifyCount;
    }

    public static function extractCount(): int
    {
        return self::$extractCount;
    }

    public static function lastOcrText(): ?string
    {
        return self::$lastOcrText;
    }

    public static function lastImageBytes(): ?string
    {
        return self::$lastImageBytes;
    }

    public static function lastImageMimeType(): ?string
    {
        return self::$lastImageMimeType;
    }

    public function classify(AiConnection $on, string $ocrText, ?DocumentType $hint = null): ClassificationResult
    {
        self::$lastConnection = $on;
        self::$classifyCount++;
        self::$lastOcrText = $ocrText;
        $this->throwIfPrimed();

        return new ClassificationResult(
            type: self::$classifyType ?? DocumentType::Receipt,
            confidence: self::$classifyConfidence,
            usage: new AiUsage(inputTokens: 500, outputTokens: 20, costUsdMicros: 3000),
            rawResponse: ['document_type' => (self::$classifyType ?? DocumentType::Receipt)->value],
        );
    }

    public function extract(AiConnection $on, string $imageBytes, string $mimeType, DocumentType $type): ExtractionResult
    {
        self::$lastConnection = $on;
        self::$extractCount++;
        self::$lastImageBytes = $imageBytes;
        self::$lastImageMimeType = $mimeType;
        $this->throwIfPrimed();

        $document = self::$document ?? new ExtractedReceipt(
            merchantName: 'FAKE',
            merchantAddress: null,
            occurredAt: null,
            currency: 'HUF',
            totalMinor: 0,
            discountMinor: null,
            paymentMethod: null,
            items: [],
        );

        return new ExtractionResult(
            document: $document,
            confidence: self::$extractConfidence,
            usage: new AiUsage(inputTokens: 4000, outputTokens: 400, costUsdMicros: 30000),
            // Mirrors the real provider, where `document` is parsed from this
            // same payload via DocumentMapper — downstream steps read the
            // artifact's raw payload, not the VO, so the two must agree here too.
            rawResponse: $document instanceof ExtractedReceipt
                ? self::receiptToRawResponse($document)
                : self::billToRawResponse($document),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function receiptToRawResponse(ExtractedReceipt $document): array
    {
        return [
            'merchant_name' => $document->merchantName,
            'merchant_address' => $document->merchantAddress,
            'occurred_at' => $document->occurredAt?->toIso8601String(),
            'currency' => $document->currency,
            'total_minor' => $document->totalMinor,
            'discount_minor' => $document->discountMinor,
            'payment_method' => $document->paymentMethod,
            'items' => array_map(static fn (ExtractedLineItem $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price_minor' => $item->unitPriceMinor,
                'total_minor' => $item->totalMinor,
            ], $document->items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function billToRawResponse(ExtractedBill $document): array
    {
        return [
            'provider_name' => $document->providerName,
            'customer_reference' => $document->customerReference,
            'currency' => $document->currency,
            'total_minor' => $document->totalMinor,
            'issued_at' => $document->issuedAt?->toIso8601String(),
            'period_start' => $document->periodStart?->toIso8601String(),
            'period_end' => $document->periodEnd?->toIso8601String(),
            'meter_reading' => $document->meterReading,
            'previous_meter_reading' => $document->previousMeterReading,
            'consumption' => $document->consumption,
            'consumption_unit' => $document->consumptionUnit,
        ];
    }

    private function throwIfPrimed(): void
    {
        if (self::$failure !== null) {
            $failure = self::$failure;
            self::$failure = null;

            throw $failure;
        }
    }
}
