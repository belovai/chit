<?php

declare(strict_types=1);

namespace Modules\Ai\Providers\Anthropic;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\ErrorType;
use Modules\Ai\Contracts\AiClient;
use Modules\Ai\Contracts\AiProvider;
use Modules\Ai\Enums\Capability;
use Modules\Ai\Services\CostCalculator;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\ModelDescriptor;
use Modules\Ai\ValueObjects\ModelPricing;
use Modules\Ai\ValueObjects\SettingField;
use Modules\Ai\ValueObjects\VerificationResult;
use Throwable;

final readonly class AnthropicProvider implements AiProvider
{
    public function __construct(private CostCalculator $costs) {}

    #[\Override]
    public function id(): string
    {
        return 'anthropic';
    }

    #[\Override]
    public function label(): string
    {
        return 'Anthropic';
    }

    #[\Override]
    public function models(): array
    {
        $all = [Capability::Vision, Capability::JsonSchema, Capability::PromptCache];

        return [
            new ModelDescriptor('claude-opus-5', 'Claude Opus 5', $all, new ModelPricing(5.00, 25.00, 0.50)),
            new ModelDescriptor('claude-sonnet-5', 'Claude Sonnet 5', $all, new ModelPricing(3.00, 15.00, 0.30)),
            new ModelDescriptor('claude-haiku-4-5', 'Claude Haiku 4.5', $all, new ModelPricing(1.00, 5.00, 0.10)),
        ];
    }

    #[\Override]
    public function model(string $id): ?ModelDescriptor
    {
        return array_find($this->models(), fn ($model) => $model->id === $id);
    }

    #[\Override]
    public function settingsSchema(): array
    {
        return [
            SettingField::int('max_tokens', default: 8000, min: 1, max: 64_000),
            SettingField::enum('effort', default: 'low', options: ['low', 'medium', 'high', 'xhigh', 'max']),
        ];
    }

    /**
     * The cheapest call that still proves both the key and the model: one
     * token of output. A models-list call would not catch a key that lacks
     * access to the chosen model.
     */
    #[\Override]
    public function verify(string $apiKey, string $model): VerificationResult
    {
        if ($this->model($model) === null) {
            return VerificationResult::failed('Unknown model ['.$model.'].');
        }

        try {
            new Client(apiKey: $apiKey)->messages->create(
                maxTokens: 1,
                messages: [['role' => 'user', 'content' => 'ping']],
                model: $model,
            );
        } catch (APIStatusException $exception) {
            // Same distinction the client makes: out of credit is not a bad key.
            return $exception->type === ErrorType::BILLING_ERROR
                ? VerificationResult::unusable($exception->getMessage())
                : VerificationResult::failed($exception->getMessage());
        } catch (Throwable $exception) {
            return VerificationResult::failed($exception->getMessage());
        }

        return VerificationResult::ok();
    }

    #[\Override]
    public function client(AiConnection $connection): AiClient
    {
        return new AnthropicClient($connection, $this->costs, $this);
    }
}
