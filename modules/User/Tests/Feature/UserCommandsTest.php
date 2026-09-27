<?php

declare(strict_types=1);

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\User\Enums\UserRole;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_user_from_prompted_input(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Ada')
            ->expectsQuestion('Email', 'ada@example.com')
            ->expectsQuestion('Password', 'secret123')
            ->expectsQuestion('Confirm password', 'secret123')
            ->assertExitCode(0);

        $user = User::query()->sole();

        $this->assertSame('Ada', $user->name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    #[Test]
    public function create_fails_on_mismatched_passwords(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Ada')
            ->expectsQuestion('Email', 'ada@example.com')
            ->expectsQuestion('Password', 'secret123')
            ->expectsQuestion('Confirm password', 'other123')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function create_fails_on_a_taken_email(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Ada')
            ->expectsQuestion('Email', 'ada@example.com')
            ->expectsQuestion('Password', 'secret123')
            ->expectsQuestion('Confirm password', 'secret123')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function create_fails_on_a_too_short_password(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Ada')
            ->expectsQuestion('Email', 'ada@example.com')
            ->expectsQuestion('Password', '123')
            ->expectsQuestion('Confirm password', '123')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function it_resets_the_password_and_revokes_all_tokens(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $user->createToken('api');

        $this->artisan('user:reset-password', ['email' => 'ada@example.com'])
            ->expectsQuestion('New password', 'newsecret')
            ->expectsQuestion('Confirm password', 'newsecret')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertTrue(Hash::check('newsecret', $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    #[Test]
    public function reset_fails_for_an_unknown_email(): void
    {
        $this->artisan('user:reset-password', ['email' => 'nobody@example.com'])
            ->assertExitCode(1);
    }

    #[Test]
    public function reset_keeps_the_old_password_on_invalid_input(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('user:reset-password', ['email' => 'ada@example.com'])
            ->expectsQuestion('New password', '123')
            ->expectsQuestion('Confirm password', '123')
            ->assertExitCode(1);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
