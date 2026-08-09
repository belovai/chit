<?php

declare(strict_types=1);

namespace Modules\Ai\Actions;

use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Enums\FailureKind;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Registries\ProviderRegistry;

final readonly class VerifyAiCredential
{
    public function __construct(private ProviderRegistry $providers) {}

    public function handle(AiCredential $credential): AiCredential
    {
        $result = $this->providers->get($credential->provider)
            ->verify($credential->api_key, $credential->model);

        $credential->update(match (true) {
            $result->ok => [
                'status' => CredentialStatus::Verified,
                'last_verified_at' => now(),
                'failure_count' => 0,
                'last_error' => null,
            ],
            // Nothing is wrong with the key, so it keeps its place: the user
            // sorts the account out at the provider and verifies again.
            $result->kind === FailureKind::Unusable => [
                'status' => CredentialStatus::Suspended,
                'last_error' => $result->message,
            ],
            default => [
                'status' => CredentialStatus::Disabled,
                'is_active' => false,
                'last_error' => $result->message,
            ],
        });

        return $credential->refresh();
    }
}
