<?php

declare(strict_types=1);

namespace Modules\Ai\Services;

use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Enums\FailureKind;
use Modules\Ai\Events\AiCredentialDisabled;
use Modules\Ai\Events\AiCredentialSuspended;
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

    public function failed(int $credentialId, string $error, FailureKind $kind): void
    {
        $credential = AiCredential::query()->find($credentialId);

        if ($credential === null) {
            return;
        }

        match ($kind) {
            // Rate limits and outages say nothing about the key's validity.
            FailureKind::Transient, FailureKind::Permanent => $credential->update(['last_error' => $error]),
            FailureKind::Unusable => $this->suspend($credential, $error),
            FailureKind::AuthFailure => $this->countAuthFailure($credential, $error),
        };
    }

    /**
     * Held out of use without a mark against it: failure_count and is_active
     * are left alone, so topping up and verifying is the whole way back.
     */
    private function suspend(AiCredential $credential, string $error): void
    {
        $alreadySuspended = $credential->status === CredentialStatus::Suspended;

        $credential->update([
            'status' => CredentialStatus::Suspended,
            'last_error' => $error,
        ]);

        if (!$alreadySuspended) {
            AiCredentialSuspended::dispatch($credential->id, $credential->owner_id, $error);
        }
    }

    private function countAuthFailure(AiCredential $credential, string $error): void
    {
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
