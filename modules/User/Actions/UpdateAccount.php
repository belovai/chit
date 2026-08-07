<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Modules\User\Models\User;

final class UpdateAccount
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(User $user, array $validated): User
    {
        $attributes = $validated;

        // Nincs még e-mail megerősítő folyamat, de a korábbi megerősítés az új
        // címre nem érvényes — inkább null, mint hamis "verified" állapot.
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $attributes['email_verified_at'] = null;
        }

        $user->forceFill($attributes)->save();

        return $user->refresh();
    }
}
