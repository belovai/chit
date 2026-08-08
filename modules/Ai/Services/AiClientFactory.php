<?php

declare(strict_types=1);

namespace Modules\Ai\Services;

use Modules\Ai\Contracts\AiClient;
use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\Support\RecordingAiClient;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\UsageContext;

final class AiClientFactory
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly UsageRecorder $usage,
        private readonly CredentialHealth $health,
    ) {}

    public function for(AiConnection $connection, ?UsageContext $context = null): AiClient
    {
        return new RecordingAiClient(
            inner: $this->providers->get($connection->provider)->client($connection),
            connection: $connection,
            context: $context ?? UsageContext::none(),
            usage: $this->usage,
            health: $this->health,
        );
    }
}
