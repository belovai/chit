<?php

declare(strict_types=1);

namespace Modules\Ai\Actions;

use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Registries\ProviderRegistry;

final class VerifyAiCredential
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function handle(AiCredential $credential): AiCredential
    {
        $result = $this->providers->get($credential->provider)
            ->verify($credential->api_key, $credential->model);

        $credential->update($result->ok
            ? [
                'status' => CredentialStatus::Verified,
                'last_verified_at' => now(),
                'failure_count' => 0,
                'last_error' => null,
            ]
            : [
                'status' => CredentialStatus::Disabled,
                'is_active' => false,
                'last_error' => $result->message,
            ]);

        return $credential->refresh();
    }
}
