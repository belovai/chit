<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Modules\User\Models\User;

final class UpdateUser
{
    public function handle(User $user, array $validated): User
    {
        $user->update($validated);

        return $user->fresh();
    }
}
