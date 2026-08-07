<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Modules\User\Jobs\PurgeUserData;
use Modules\User\Models\User;

final class DeleteAccount
{
    /**
     * A kérés csak soft delete-el és kilépteti a felhasználót minden eszközön;
     * a végleges takarítás háttérben, a PurgeUserData jobban fut.
     *
     * @throws \Throwable
     */
    public function handle(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();

        PurgeUserData::dispatch($user->id);
    }
}
