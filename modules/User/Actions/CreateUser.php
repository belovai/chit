<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Modules\User\Models\User;

final class CreateUser
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(array $validated): User
    {
        return User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
    }
}
