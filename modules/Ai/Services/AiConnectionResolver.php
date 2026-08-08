<?php

declare(strict_types=1);

namespace Modules\Ai\Services;

use Modules\Ai\Exceptions\NoActiveAiCredentialException;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\User\Models\User;

/**
 * The only place a stored key is decrypted. Callers hand it straight to a
 * client and drop it; nothing else holds an AiConnection beyond one call.
 */
final class AiConnectionResolver
{
    public function forUser(User $user): AiConnection
    {
        $credential = $this->activeCredentialFor($user->id);

        if ($credential === null) {
            throw NoActiveAiCredentialException::forUser($user->id);
        }

        return $this->forCredential($credential);
    }

    public function forCredential(AiCredential $credential): AiConnection
    {
        if (!$credential->status->isUsable()) {
            throw NoActiveAiCredentialException::forUser($credential->owner_id);
        }

        return new AiConnection(
            provider: $credential->provider,
            model: $credential->model,
            apiKey: $credential->api_key,
            settings: $credential->settings,
            credentialId: $credential->id,
            userId: $credential->owner_id,
        );
    }

    public function activeCredentialFor(int $userId): ?AiCredential
    {
        return AiCredential::query()
            ->forUser($userId)
            ->active()
            ->first();
    }

    /**
     * Resolves a snapshotted credential id inside a worker. The key is read and
     * decrypted here, at execution time — it is never carried in a job payload.
     */
    public function forCredentialId(int $credentialId): AiConnection
    {
        $credential = AiCredential::query()->find($credentialId);

        if ($credential === null) {
            throw NoActiveAiCredentialException::missing();
        }

        return $this->forCredential($credential);
    }
}
