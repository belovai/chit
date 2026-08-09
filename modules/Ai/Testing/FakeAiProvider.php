<?php

declare(strict_types=1);

namespace Modules\Ai\Testing;

use Modules\Ai\Contracts\AiClient;
use Modules\Ai\Contracts\AiProvider;
use Modules\Ai\Enums\Capability;
use Modules\Ai\Exceptions\AiException;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\AiResponse;
use Modules\Ai\ValueObjects\AiUsage;
use Modules\Ai\ValueObjects\ModelDescriptor;
use Modules\Ai\ValueObjects\ModelPricing;
use Modules\Ai\ValueObjects\SettingField;
use Modules\Ai\ValueObjects\VerificationResult;

/**
 * Ships in the module rather than in Tests/ so other modules can bind it
 * without depending on this module's test autoload - the same reason
 * FakeDocumentAi lives in Modules\Extraction\Ai\Testing.
 */
final class FakeAiProvider implements AiProvider
{
    /** @var array<string, mixed> */
    private static array $payload = [];

    private static ?AiUsage $usage = null;

    private static ?AiException $failure = null;

    private static ?string $verificationError = null;

    private static bool $verificationUnusable = false;

    /** @var list<AiRequest> */
    private static array $calls = [];

    /** @var list<AiConnection> */
    private static array $connections = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function willRespond(array $payload, ?AiUsage $usage = null): void
    {
        self::$payload = $payload;
        self::$usage = $usage ?? new AiUsage(inputTokens: 100, outputTokens: 50, costUsdMicros: 1_750);
    }

    public static function willFail(AiException $exception): void
    {
        self::$failure = $exception;
    }

    public static function willFailVerification(string $message): void
    {
        self::$verificationError = $message;
    }

    /** Verification fails because the account cannot serve, not because the key is bad. */
    public static function willReportUnusableOnVerification(string $message): void
    {
        self::$verificationError = $message;
        self::$verificationUnusable = true;
    }

    /**
     * @return list<AiRequest>
     */
    public static function calls(): array
    {
        return self::$calls;
    }

    /**
     * @return list<AiConnection>
     */
    public static function connections(): array
    {
        return self::$connections;
    }

    public static function record(AiRequest $request, AiConnection $connection): void
    {
        self::$calls[] = $request;
        self::$connections[] = $connection;
    }

    /**
     * @throws \JsonException
     */
    public static function nextResponse(): AiResponse
    {
        if (self::$failure !== null) {
            $failure = self::$failure;
            self::$failure = null;

            throw $failure;
        }

        return new AiResponse(
            payload: self::$payload,
            text: json_encode(self::$payload, JSON_THROW_ON_ERROR),
            usage: self::$usage ?? AiUsage::none(),
        );
    }

    public static function reset(): void
    {
        self::$payload = [];
        self::$usage = null;
        self::$failure = null;
        self::$verificationError = null;
        self::$verificationUnusable = false;
        self::$calls = [];
        self::$connections = [];
    }

    #[\Override]
    public function id(): string
    {
        return 'fake';
    }

    #[\Override]
    public function label(): string
    {
        return 'Fake provider';
    }

    #[\Override]
    public function models(): array
    {
        return [
            new ModelDescriptor(
                id: 'fake-model',
                label: 'Fake model',
                capabilities: [Capability::Vision, Capability::JsonSchema, Capability::PromptCache],
                pricing: new ModelPricing(5.00, 25.00, 0.50),
            ),
            new ModelDescriptor(
                id: 'fake-text-only',
                label: 'Fake text-only model',
                capabilities: [Capability::JsonSchema],
                pricing: new ModelPricing(1.00, 5.00, 0.10),
            ),
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
            SettingField::int('max_tokens', default: 8000, min: 1, max: 64000),
            SettingField::enum('effort', default: 'low', options: ['low', 'medium', 'high']),
        ];
    }

    #[\Override]
    public function verify(string $apiKey, string $model): VerificationResult
    {
        if (self::$verificationError !== null) {
            return self::$verificationUnusable
                ? VerificationResult::unusable(self::$verificationError)
                : VerificationResult::failed(self::$verificationError);
        }

        return $this->model($model) !== null
            ? VerificationResult::ok()
            : VerificationResult::failed('Unknown model ['.$model.'].');
    }

    #[\Override]
    public function client(AiConnection $connection): AiClient
    {
        return new FakeAiClient($connection);
    }
}
