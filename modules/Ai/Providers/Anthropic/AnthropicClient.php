<?php

declare(strict_types=1);

namespace Modules\Ai\Providers\Anthropic;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\OutputConfig\Effort;
use Anthropic\Messages\TextBlock;
use Modules\Ai\Contracts\AiClient;
use Modules\Ai\Enums\Capability;
use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Services\CostCalculator;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\AiResponse;
use Modules\Ai\ValueObjects\AiUsage;
use Modules\Ai\ValueObjects\ImagePart;
use Modules\Ai\ValueObjects\TextPart;
use Throwable;

/**
 * The single file that touches the Anthropic SDK. What is vendor-specific and
 * stays here: the client call, the structured-output mechanism
 * (`outputConfig.format`), the usage field names, and the exception →
 * retryable mapping.
 */
class AnthropicClient implements AiClient
{
    public function __construct(
        private readonly AiConnection $connection,
        private readonly CostCalculator $costs,
        private readonly AnthropicProvider $provider,
    ) {}

    public function complete(AiRequest $request): AiResponse
    {
        $this->assertUsable($request);

        try {
            $message = $this->client()->messages->create(
                maxTokens: (int) $this->connection->setting('max_tokens', 8000),
                // @phpstan-ignore argument.type (the SDK's MessageParam union cannot express our block list precisely)
                messages: [['role' => 'user', 'content' => $this->contentBlocks($request)]],
                model: $this->connection->model,
                outputConfig: $this->outputConfig($request),
                // @phpstan-ignore argument.type (the SDK's TextBlockParam union cannot express our conditional cacheControl precisely)
                system: [[
                    'type' => 'text',
                    'text' => $request->system,
                    // A byte-identical system prompt across documents turns most
                    // of the input into a cache read from the second call onward.
                    ...($request->cacheSystem ? ['cacheControl' => ['type' => 'ephemeral']] : []),
                ]],
                // Structured extraction is a read, not a reasoning problem.
                // Disabling thinking is permitted at effort `high` or below.
                thinking: ['type' => 'disabled'],
            );
        } catch (RateLimitException|InternalServerException|APIConnectionException $exception) {
            throw AiException::retryable($exception->getMessage(), $exception);
        } catch (APIStatusException $exception) {
            // 4xx other than 429 — bad request, auth, permissions. Retrying cannot help.
            $errorType = $exception->type !== null ? $exception->type->value : 'api_error';

            throw AiException::permanent($errorType.': '.$exception->getMessage(), $exception);
        } catch (Throwable $exception) {
            throw AiException::permanent($exception->getMessage(), $exception);
        }

        if ($message->stopReason === 'refusal') {
            throw AiException::permanent('The model declined to process this document.');
        }

        $text = $this->firstTextBlock($message->content);

        return new AiResponse(
            payload: $request->jsonSchema !== null ? $this->decode($text) : [],
            text: $text,
            usage: $this->costs->priced($this->connection->provider, $this->connection->model, new AiUsage(
                inputTokens: $message->usage->inputTokens,
                outputTokens: $message->usage->outputTokens,
                cachedInputTokens: $message->usage->cacheReadInputTokens ?? 0,
            )),
        );
    }

    /**
     * The vendor seam. Tests override this method to return a stub client.
     */
    protected function client(): Client
    {
        return new Client(apiKey: $this->connection->apiKey);
    }

    private function assertUsable(AiRequest $request): void
    {
        if ($this->connection->apiKey === '') {
            throw AiException::permanent('No API key is configured for this connection.');
        }

        $model = $this->provider->model($this->connection->model);

        if ($model === null) {
            throw AiException::permanent('Unknown model ['.$this->connection->model.'].');
        }

        if ($request->hasImages() && !$model->supports(Capability::Vision)) {
            throw AiException::permanent(
                'Model ['.$model->id.'] cannot accept images.',
            );
        }

        if ($request->jsonSchema !== null && !$model->supports(Capability::JsonSchema)) {
            throw AiException::permanent(
                'Model ['.$model->id.'] cannot return structured output.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function outputConfig(AiRequest $request): array
    {
        $config = ['effort' => Effort::from((string) $this->connection->setting('effort', 'low'))];

        if ($request->jsonSchema !== null) {
            $config['format'] = ['type' => 'json_schema', 'schema' => $request->jsonSchema];
        }

        return $config;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentBlocks(AiRequest $request): array
    {
        $blocks = [];

        foreach ($request->content as $part) {
            $blocks[] = match (true) {
                $part instanceof TextPart => ['type' => 'text', 'text' => $part->text],
                $part instanceof ImagePart => [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'mediaType' => $part->mimeType,
                        'data' => base64_encode($part->bytes),
                    ],
                ],
                default => throw AiException::permanent('Unsupported content part.'),
            };
        }

        return $blocks;
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
