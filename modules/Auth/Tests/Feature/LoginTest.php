<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_with_valid_credentials_returns_token_and_records_last_login(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertIsString($response->json('data.token'));

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    #[Test]
    public function login_rejects_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.email.0', 'auth.invalid_credentials');
    }

    #[Test]
    public function login_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.email.0', 'auth.invalid_credentials');
    }

    #[Test]
    public function login_requires_email_and_returns_generic_required_code(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.email.0', 'required');
    }
}
