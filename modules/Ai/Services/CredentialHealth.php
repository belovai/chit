<?php

declare(strict_types=1);

namespace Modules\Ai\Services;

use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Events\AiCredentialDisabled;
use Modules\Ai\Models\AiCredential;

/**
 * Keeps a credential's health current so a revoked key stops burning jobs
 * instead of failing every run until someone notices.
 */
final class CredentialHealth
{
    public function succeeded(int $credentialId): void
    {
        AiCredential::query()->whereKey($credentialId)->update([
            'status' => CredentialStatus::Verified,
            'failure_count' => 0,
            'last_error' => null,
            'last_used_at' => now(),
        ]);
    }

    public function failed(int $credentialId, string $error, bool $isAuthFailure): void
    {
        $credential = AiCredential::query()->find($credentialId);

        if ($credential === null) {
            return;
        }

        if (!$isAuthFailure) {
            // Rate limits and outages say nothing about the key's validity.
            $credential->update(['last_error' => $error]);

            return;
        }

        $failures = $credential->failure_count + 1;
        $threshold = (int) config('ai.auth_failure_threshold', 3);
        $disabled = $failures >= $threshold;

        $credential->update([
            'failure_count' => $failures,
            'last_error' => $error,
            'status' => $disabled ? CredentialStatus::Disabled : CredentialStatus::Failing,
            'is_active' => $disabled ? false : $credential->is_active,
        ]);

        if ($disabled) {
            AiCredentialDisabled::dispatch($credential->id, $credential->owner_id, $error);
        }
    }
}
