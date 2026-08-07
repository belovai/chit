<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Laravel\Sanctum\PersonalAccessToken;
use Modules\User\Models\User;

final class ChangeAccountPassword
{
    /**
     * Current password is checked in the FormRequest's `current_password:sanctum`
     * rule, so a wrong password returns a 422 together with the other fields.
     */
    public function handle(User $user, string $password): void
    {
        $user->update(['password' => $password]);

        $this->revokeOtherTokens($user);
    }

    /**
     * After a password change, tokens issued on other devices become invalid —
     * only the one currently in use survives, so the caller isn't logged out immediately.
     */
    private function revokeOtherTokens(User $user): void
    {
        // Sanctum's docblock doesn't mark this nullable, yet outside HTTP (e.g.
        // an Action invoked from the console) there's no current token.
        /** @var PersonalAccessToken|null $current */
        $current = $user->currentAccessToken();
        $query = $user->tokens();

        if ($current instanceof PersonalAccessToken) {
            $query->whereKeyNot($current->getKey());
        }

        $query->delete();
    }
}
