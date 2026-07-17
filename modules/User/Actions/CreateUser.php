<?php

declare(strict_types=1);

namespace Modules\User\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\User\Models\User;

final class CreateUser
{
    public function handle(array $validated): Authenticatable
    {
        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return $user->fresh();
    }
}
