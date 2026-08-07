<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Laravel\Sanctum\PersonalAccessToken;
use Modules\User\Models\User;

final class ChangeAccountPassword
{
    /**
     * A jelenlegi jelszó ellenőrzése a FormRequest `current_password:sanctum`
     * szabályában történik, hogy hibás jelszó 422-t adjon a többi mezővel
     * együtt.
     */
    public function handle(User $user, string $password): void
    {
        $user->update(['password' => $password]);

        $this->revokeOtherTokens($user);
    }

    /**
     * Jelszóváltás után a többi eszközön kiadott token érvénytelen — csak az
     * éppen használt marad életben, hogy a hívó ne essen ki azonnal.
     */
    private function revokeOtherTokens(User $user): void
    {
        // Sanctum docblockja nem jelöli nullable-nek, pedig HTTP-n kívül (pl.
        // konzolról hívott Action) nincs aktuális token.
        /** @var PersonalAccessToken|null $current */
        $current = $user->currentAccessToken();
        $query = $user->tokens();

        if ($current instanceof PersonalAccessToken) {
            $query->whereKeyNot($current->getKey());
        }

        $query->delete();
    }
}
