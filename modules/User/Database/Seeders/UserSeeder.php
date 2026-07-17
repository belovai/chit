<?php

declare(strict_types=1);

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\User\Enums\UserRole;
use Modules\User\Models\User;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::query()->updateOrCreate([
            'email' => 'sysadmin@example.com',
        ], [
            'name' => 'sysadmin',
            'hash_id' => 'aaaaa12345',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => UserRole::Admin,
        ]);
    }
}
