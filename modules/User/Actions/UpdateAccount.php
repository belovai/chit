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

        // There's no email verification flow yet, but the prior verification
        // doesn't apply to the new address — null beats a false "verified" state.
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $attributes['email_verified_at'] = null;
        }

        $user->forceFill($attributes)->save();

        return $user->refresh();
    }
}
