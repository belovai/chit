<?php

declare(strict_types=1);

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AccountTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/account');

        $response->assertOk();
        $response->assertJsonPath('data.email', 'ada@example.com');
    }

    #[Test]
    public function show_requires_authentication(): void
    {
        $this->getJson('/api/account')->assertUnauthorized();
    }

    #[Test]
    public function update_changes_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
        ]);

        $response = $this->withToken($this->tokenFor($user))->patchJson('/api/account', [
            'name' => 'Ada Lovelace',
            'email' => 'lovelace@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Ada Lovelace');
        $response->assertJsonPath('data.email', 'lovelace@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ada Lovelace',
            'email' => 'lovelace@example.com',
            'email_verified_at' => null,
        ]);
    }

    #[Test]
    public function update_accepts_a_single_field(): void
    {
        $user = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);

        $response = $this->withToken($this->tokenFor($user))->patchJson('/api/account', [
            'name' => 'Ada Lovelace',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'ada@example.com');
    }

    #[Test]
    public function update_allows_keeping_the_own_email(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->withToken($this->tokenFor($user))->patchJson('/api/account', [
            'email' => 'ada@example.com',
        ]);

        $response->assertOk();
    }

    #[Test]
    public function update_rejects_an_email_taken_by_another_user(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        User::factory()->create(['email' => 'grace@example.com']);

        $response = $this->withToken($this->tokenFor($user))->patchJson('/api/account', [
            'email' => 'grace@example.com',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.email.0', 'unique');
    }

    #[Test]
    public function update_rejects_an_empty_name(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->patchJson('/api/account', [
            'name' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.name.0', 'required');
    }

    #[Test]
    public function password_is_changed_with_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $response = $this->withToken($this->tokenFor($user))->putJson('/api/account/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    #[Test]
    public function password_change_revokes_other_tokens_but_keeps_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $user->createToken('other-device');
        $token = $this->tokenFor($user);

        $this->withToken($token)->putJson('/api/account/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
        ])->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->withToken($token)->getJson('/api/account')->assertOk();
    }

    #[Test]
    public function password_change_rejects_a_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $response = $this->withToken($this->tokenFor($user))->putJson('/api/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.current_password.0', 'auth.invalid_password');
        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }

    #[Test]
    public function password_change_rejects_a_too_short_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $response = $this->withToken($this->tokenFor($user))->putJson('/api/account/password', [
            'current_password' => 'old-password',
            'password' => 'short',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.password.0', 'auth.password_too_short');
    }

    #[Test]
    public function password_change_rejects_reusing_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $response = $this->withToken($this->tokenFor($user))->putJson('/api/account/password', [
            'current_password' => 'old-password',
            'password' => 'old-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.password.0', 'auth.password_must_differ');
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }
}
