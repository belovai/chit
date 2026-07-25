<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegisterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.user.email', 'ada@example.com');
        $this->assertIsString($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
        ]);
    }

    #[Test]
    public function register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.email.0', 'unique');
    }

    #[Test]
    public function register_requires_name_and_returns_generic_required_code(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'ada@example.com',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.name.0', 'required');
    }

    #[Test]
    public function register_rejects_short_password_with_specific_code(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'short',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.password.0', 'auth.password_too_short');
    }
}
