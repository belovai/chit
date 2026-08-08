<?php

declare(strict_types=1);

namespace Modules\Ai\Registries;

use Modules\Ai\Contracts\AiProvider;
use Modules\Ai\Exceptions\UnknownAiProviderException;

final class ProviderRegistry
{
    /** @var array<string, AiProvider> */
    private array $providers = [];

    public function register(AiProvider $provider): void
    {
        $this->providers[$provider->id()] = $provider;
    }

    /**
     * @return list<AiProvider>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    public function get(string $id): AiProvider
    {
        return $this->providers[$id] ?? throw UnknownAiProviderException::forId($id);
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }
}
