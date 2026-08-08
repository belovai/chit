<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

/**
 * A resolved credential, ready to call with. Constructed only by
 * AiConnectionResolver — never assembled by hand outside the Ai module.
 *
 * Holds a plaintext API key. Never log it, never serialise it into a queue
 * payload, never put it in an exception message.
 */
final readonly class AiConnection
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $apiKey,
        public array $settings = [],
        public ?int $credentialId = null,
        public ?int $userId = null,
    ) {}

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
