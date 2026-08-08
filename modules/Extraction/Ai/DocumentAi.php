<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai;

use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Services\AiClientFactory;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\ImagePart;
use Modules\Ai\ValueObjects\TextPart;
use Modules\Ai\ValueObjects\UsageContext;
use Modules\Extraction\Ai\Contracts\DocumentClassifier;
use Modules\Extraction\Ai\Contracts\DocumentExtractor;
use Modules\Extraction\Ai\Prompts\ClassificationPrompt;
use Modules\Extraction\Ai\Prompts\ReceiptPrompt;
use Modules\Extraction\Ai\Prompts\UtilityBillPrompt;
use Modules\Extraction\Ai\Schemas\ClassificationSchema;
use Modules\Extraction\Ai\Schemas\ReceiptSchema;
use Modules\Extraction\Ai\Schemas\UtilityBillSchema;
use Modules\Extraction\Ai\Support\DocumentMapper;
use Modules\Extraction\Ai\ValueObjects\ClassificationResult;
use Modules\Extraction\Ai\ValueObjects\ExtractionResult;
use Modules\Extraction\Enums\DocumentType;

/**
 * The domain half of document AI: which prompt, which schema, and how the
 * payload becomes a DTO. Which vendor answers, on whose key, and with what
 * token budget is decided entirely by the AiConnection handed in.
 */
final class DocumentAi implements DocumentClassifier, DocumentExtractor
{
    public function __construct(private readonly AiClientFactory $clients) {}

    public function classify(AiConnection $on, string $ocrText, ?DocumentType $hint = null): ClassificationResult
    {
        $response = $this->clients
            ->for($on, new UsageContext('extraction.classify'))
            ->complete(new AiRequest(
                system: ClassificationPrompt::system(),
                content: [new TextPart(ClassificationPrompt::user($ocrText, $hint))],
                jsonSchema: ClassificationSchema::json(),
                cacheSystem: true,
            ));

        return new ClassificationResult(
            type: DocumentType::parse($response->payload['document_type'] ?? null),
            confidence: DocumentMapper::confidenceFrom($response->payload),
            usage: $response->usage,
            rawResponse: $response->payload,
        );
    }

    public function extract(
        AiConnection $on,
        string $imageBytes,
        string $mimeType,
        DocumentType $type,
    ): ExtractionResult {
        [$system, $instruction, $schema] = match ($type) {
            DocumentType::Receipt => [ReceiptPrompt::system(), ReceiptPrompt::user(), ReceiptSchema::json()],
            DocumentType::UtilityBill => [UtilityBillPrompt::system(), UtilityBillPrompt::user(), UtilityBillSchema::json()],
            DocumentType::Unknown => throw AiException::permanent('Cannot extract from an unclassified document.'),
        };

        $response = $this->clients
            ->for($on, new UsageContext('extraction.extract'))
            ->complete(new AiRequest(
                system: $system,
                content: [new ImagePart($imageBytes, $mimeType), new TextPart($instruction)],
                jsonSchema: $schema,
                cacheSystem: true,
            ));

        return new ExtractionResult(
            document: $type === DocumentType::Receipt
                ? DocumentMapper::toReceipt($response->payload)
                : DocumentMapper::toBill($response->payload),
            confidence: DocumentMapper::confidenceFrom($response->payload),
            usage: $response->usage,
            rawResponse: $response->payload,
        );
    }
}
