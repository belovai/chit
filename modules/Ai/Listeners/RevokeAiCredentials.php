<?php

declare(strict_types=1);

namespace Modules\Ai\Listeners;

use Modules\Ai\Models\AiCredential;
use Modules\User\Events\AccountDeleted;

/**
 * Stored API keys are the one thing that must not survive the gap between a
 * deletion request and the background purge: they are live credentials to a
 * third-party account the user is still paying for.
 */
final class RevokeAiCredentials
{
    public function handle(AccountDeleted $event): void
    {
        AiCredential::query()->where('owner_id', $event->userId)->delete();
    }
}
