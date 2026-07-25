<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Auth\ValueObjects\AccessToken;
use Modules\User\Models\User;

final class AuthenticateUser
{
    public function handle(string $email, string $password): AccessToken
    {
        $user = User::query()->where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['auth.invalid_credentials'],
            ]);
        }

        $user->update(['last_login_at' => now()]);

        return new AccessToken(
            plainText: $user->createToken('api')->plainTextToken,
            user: $user,
        );
    }
}
