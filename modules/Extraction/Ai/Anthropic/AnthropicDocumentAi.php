<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Anthropic;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\OutputConfig\Effort;
use Anthropic\Messages\TextBlock;
use Modules\Extraction\Ai\Contracts\DocumentClassifier;
use Modules\Extraction\Ai\Contracts\DocumentExtractor;
use Modules\Extraction\Ai\Prompts\ClassificationPrompt;
use Modules\Extraction\Ai\Prompts\ReceiptPrompt;
use Modules\Extraction\Ai\Prompts\UtilityBillPrompt;
use Modules\Extraction\Ai\Schemas\ClassificationSchema;
use Modules\Extraction\Ai\Schemas\ReceiptSchema;
use Modules\Extraction\Ai\Schemas\UtilityBillSchema;
use Modules\Extraction\Ai\Support\CostCalculator;
use Modules\Extraction\Ai\Support\DocumentMapper;
use Modules\Extraction\Ai\ValueObjects\AiUsage;
use Modules\Extraction\Ai\ValueObjects\ClassificationResult;
use Modules\Extraction\Ai\ValueObjects\ExtractionResult;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Exceptions\AiException;
use Throwable;

/**
 * Reference implementation of the provider-agnostic AI layer.
 *
 * Everything domain-shaped — prompt text, JSON schema, payload → DTO mapping,
 * pricing — lives outside this class. What is Anthropic-specific and stays here:
 * the client call, the structured-output mechanism (`outputConfig.format`), the
 * usage field names, and the exception → retryable mapping.
 *
 * A second provider implements `callModel()` differently and reuses the rest.
 */
class AnthropicDocumentAi implements DocumentClassifier, DocumentExtractor
{
    public function __construct(private readonly CostCalculator $costs) {}

    public function classify(string $ocrText, ?DocumentType $hint = null): ClassificationResult
    {
        $response = $this->callModel(
            system: ClassificationPrompt::system(),
            user: ClassificationPrompt::user($ocrText, $hint),
            schema: ClassificationSchema::json(),
        );

        return new ClassificationResult(
            type: DocumentType::parse($response['payload']['document_type'] ?? null),
            confidence: DocumentMapper::confidenceFrom($response['payload']),
            usage: $response['usage'],
            rawResponse: $response['payload'],
        );
    }

    public function extract(string $imageBytes, string $mimeType, DocumentType $type): ExtractionResult
    {
        [$system, $instruction, $schema] = match ($type) {
            DocumentType::Receipt => [ReceiptPrompt::system(), ReceiptPrompt::user(), ReceiptSchema::json()],
            DocumentType::UtilityBill => [UtilityBillPrompt::system(), UtilityBillPrompt::user(), UtilityBillSchema::json()],
            DocumentType::Unknown => throw AiException::permanent('Cannot extract from an unclassified document.'),
        };

        $response = $this->callModel($system, [
            [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'mediaType' => $mimeType,
                    'data' => base64_encode($imageBytes),
                ],
            ],
            ['type' => 'text', 'text' => $instruction],
        ], $schema);

        return new ExtractionResult(
            document: $type === DocumentType::Receipt
                ? DocumentMapper::toReceipt($response['payload'])
                : DocumentMapper::toBill($response['payload']),
            confidence: DocumentMapper::confidenceFrom($response['payload']),
            usage: $response['usage'],
            rawResponse: $response['payload'],
        );
    }

    /**
     * The single seam that touches the vendor SDK. Tests override this.
     *
     * @param  string|list<array<string, mixed>>  $user
     * @param  array<string, mixed>  $schema
     * @return array{payload: array<string, mixed>, usage: AiUsage}
     */
    protected function callModel(string $system, string|array $user, array $schema): array
    {
        $model = (string) config('extraction.ai.model');

        try {
            $message = $this->client()->messages->create(
                maxTokens: (int) config('extraction.ai.max_tokens'),
                messages: [['role' => 'user', 'content' => $user]],
                // The system prompt is byte-identical across every document of a
                // given type, so caching it turns most of the input into a
                // cache read from the second receipt onward.
                model: $model,
                outputConfig: [
                    'effort' => Effort::from((string) config('extraction.ai.effort')),
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                // Structured extraction is a read, not a reasoning problem.
                // Disabling thinking is permitted at effort `high` or below.
                system: [[
                    'type' => 'text',
                    'text' => $system,
                    'cacheControl' => ['type' => 'ephemeral'],
                ]],
                thinking: ['type' => 'disabled'],
            );
        } catch (RateLimitException|InternalServerException|APIConnectionException $exception) {
            throw AiException::retryable($exception->getMessage(), $exception);
        } catch (APIStatusException $exception) {
            // 4xx other than 429 — bad request, auth, permissions. Retrying cannot help.
            $errorType = $exception->type !== null ? $exception->type->value : 'api_error';

            throw AiException::permanent(
                $errorType.': '.$exception->getMessage(),
                $exception,
            );
        } catch (Throwable $exception) {
            throw AiException::permanent($exception->getMessage(), $exception);
        }

        if ($message->stopReason === 'refusal') {
            throw AiException::permanent('The model declined to process this document.');
        }

        return [
            'payload' => $this->decode($this->firstTextBlock($message->content)),
            'usage' => new AiUsage(
                inputTokens: $message->usage->inputTokens,
                outputTokens: $message->usage->outputTokens,
                cachedInputTokens: $message->usage->cacheReadInputTokens ?? 0,
                costUsdMicros: $this->costs->usdMicros(
                    model: $model,
                    inputTokens: $message->usage->inputTokens,
                    outputTokens: $message->usage->outputTokens,
                    cachedInputTokens: $message->usage->cacheReadInputTokens ?? 0,
                ),
            ),
        ];
    }

    private function client(): Client
    {
        $key = config('extraction.ai.api_key');

        if (!is_string($key) || $key === '') {
            throw AiException::permanent('ANTHROPIC_API_KEY is not configured.');
        }

        return new Client(apiKey: $key);
    }

    /**
     * The content array is polymorphic — a thinking block can precede the text
     * block, so indexing content[0] blindly is a bug even with thinking off.
     *
     * @param  iterable<object>  $content
     */
    private function firstTextBlock(iterable $content): string
    {
        foreach ($content as $block) {
            if ($block instanceof TextBlock) {
                return $block->text;
            }
        }

        throw AiException::permanent('The model returned no text block.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw AiException::permanent('The model returned output that is not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
