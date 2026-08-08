<?php

declare(strict_types=1);

namespace Modules\Ai\Policies;

use Modules\Ai\Models\AiCredential;
use Modules\User\Models\User;

final class AiCredentialPolicy
{
    public function view(User $user, AiCredential $credential): bool
    {
        return $user->id === $credential->owner_id;
    }

    public function update(User $user, AiCredential $credential): bool
    {
        return $user->id === $credential->owner_id;
    }

    public function delete(User $user, AiCredential $credential): bool
    {
        return $user->id === $credential->owner_id;
    }
}
