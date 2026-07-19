<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use Modules\User\Models\User;

final class LogoutUser
{
    public function handle(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
