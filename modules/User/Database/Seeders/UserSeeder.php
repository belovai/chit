<?php

declare(strict_types=1);

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Ai\Actions\CreateAiCredential;
use Modules\User\Enums\UserRole;
use Modules\User\Models\User;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $sysadmin = User::query()->updateOrCreate([
            'email' => 'sysadmin@example.com',
        ], [
            'name' => 'sysadmin',
            'hash_id' => 'aaaaa12345',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => UserRole::Admin,
        ]);

        $devKey = config('ai.dev_api_key');

        // Optional: a fresh checkout without a key still seeds cleanly, it just
        // cannot process documents until someone runs ai:credential:create.
        if (is_string($devKey) && $devKey !== '') {
            app(CreateAiCredential::class)->handle($sysadmin->id, [
                'provider' => 'anthropic',
                'label' => 'Development key',
                'api_key' => $devKey,
                'model' => 'claude-opus-5',
                'settings' => ['max_tokens' => 8000, 'effort' => 'low'],
            ]);
        }
    }
}
