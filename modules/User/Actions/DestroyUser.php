<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Modules\User\Models\User;

final class DestroyUser
{
    /**
     * @throws \Throwable
     */
    public function handle(User $user): void
    {
        $user->delete();
    }
}
