<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Modules\User\Jobs\PurgeUserData;
use Modules\User\Models\User;

final class DeleteAccount
{
    /**
     * The request only soft-deletes and logs the user out on every device;
     * permanent cleanup runs in the background, in the PurgeUserData job.
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
