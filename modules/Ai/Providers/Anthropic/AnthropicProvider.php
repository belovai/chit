<?php

declare(strict_types=1);

namespace Modules\Ai\Providers\Anthropic;

use Anthropic\Client;
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

final class AnthropicProvider implements AiProvider
{
    public function __construct(private readonly CostCalculator $costs) {}

    public function id(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return 'Anthropic';
    }

    public function models(): array
    {
        $all = [Capability::Vision, Capability::JsonSchema, Capability::PromptCache];

        return [
            new ModelDescriptor('claude-opus-5', 'Claude Opus 5', $all, new ModelPricing(5.00, 25.00, 0.50)),
            new ModelDescriptor('claude-sonnet-5', 'Claude Sonnet 5', $all, new ModelPricing(3.00, 15.00, 0.30)),
            new ModelDescriptor('claude-haiku-4-5', 'Claude Haiku 4.5', $all, new ModelPricing(1.00, 5.00, 0.10)),
        ];
    }

    public function model(string $id): ?ModelDescriptor
    {
        foreach ($this->models() as $model) {
            if ($model->id === $id) {
                return $model;
            }
        }

        return null;
    }

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
    public function verify(string $apiKey, string $model): VerificationResult
    {
        if ($this->model($model) === null) {
            return VerificationResult::failed('Unknown model ['.$model.'].');
        }

        try {
            (new Client(apiKey: $apiKey))->messages->create(
                maxTokens: 1,
                messages: [['role' => 'user', 'content' => 'ping']],
                model: $model,
            );
        } catch (Throwable $exception) {
            return VerificationResult::failed($exception->getMessage());
        }

        return VerificationResult::ok();
    }

    public function client(AiConnection $connection): AiClient
    {
        return new AnthropicClient($connection, $this->costs, $this);
    }
}
