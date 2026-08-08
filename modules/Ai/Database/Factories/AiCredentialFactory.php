<?php

declare(strict_types=1);

namespace Modules\Ai\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Models\AiCredential;
use Modules\User\Models\User;

/**
 * @extends Factory<AiCredential>
 */
final class AiCredentialFactory extends Factory
{
    protected $model = AiCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'sk-fake-'.$this->faker->unique()->bothify('??????????');

        return [
            'owner_id' => User::factory(),
            'provider' => 'fake',
            'label' => $this->faker->words(2, true),
            'api_key' => $key,
            'key_last_four' => AiCredential::lastFour($key),
            'key_fingerprint' => AiCredential::fingerprint($key),
            'model' => 'fake-model',
            'settings' => ['max_tokens' => 8000, 'effort' => 'low'],
            'is_active' => false,
            'status' => CredentialStatus::Verified,
            'last_verified_at' => now(),
            'failure_count' => 0,
        ];
    }

    public function verified(): self
    {
        return $this->state(fn (): array => [
            'status' => CredentialStatus::Verified,
            'last_verified_at' => now(),
            'failure_count' => 0,
            'last_error' => null,
        ]);
    }

    public function active(): self
    {
        return $this->verified()->state(fn (): array => ['is_active' => true]);
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => [
            'status' => CredentialStatus::Disabled,
            'is_active' => false,
            'failure_count' => 3,
            'last_error' => 'authentication_error: invalid x-api-key',
        ]);
    }
}
