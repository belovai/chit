<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ai\Actions\ActivateAiCredential;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Exceptions\NoActiveAiCredentialException;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Services\AiConnectionResolver;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AiConnectionResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_the_active_credential_into_a_connection(): void
    {
        $user = User::factory()->create();
        AiCredential::factory()->for($user, 'owner')->active()->create([
            'provider' => 'fake',
            'model' => 'fake-model',
            'api_key' => 'sk-fake-live',
            'settings' => ['max_tokens' => 4000, 'effort' => 'medium'],
        ]);

        $connection = app(AiConnectionResolver::class)->forUser($user);

        $this->assertSame('fake', $connection->provider);
        $this->assertSame('fake-model', $connection->model);
        $this->assertSame('sk-fake-live', $connection->apiKey);
        $this->assertSame(4000, $connection->setting('max_tokens'));
        $this->assertSame($user->id, $connection->userId);
    }

    #[Test]
    public function a_user_without_any_credential_cannot_be_resolved(): void
    {
        $this->expectException(NoActiveAiCredentialException::class);

        app(AiConnectionResolver::class)->forUser(User::factory()->create());
    }

    #[Test]
    public function an_inactive_credential_does_not_count(): void
    {
        $user = User::factory()->create();
        AiCredential::factory()->for($user, 'owner')->verified()->create(['is_active' => false]);

        $this->expectException(NoActiveAiCredentialException::class);

        app(AiConnectionResolver::class)->forUser($user);
    }

    #[Test]
    public function a_disabled_credential_is_not_usable_even_when_active(): void
    {
        $user = User::factory()->create();
        AiCredential::factory()->for($user, 'owner')->create([
            'is_active' => true,
            'status' => CredentialStatus::Disabled,
        ]);

        $this->expectException(NoActiveAiCredentialException::class);

        app(AiConnectionResolver::class)->forUser($user);
    }

    #[Test]
    public function one_users_credential_is_never_resolved_for_another(): void
    {
        $ada = User::factory()->create();
        $grace = User::factory()->create();

        AiCredential::factory()->for($ada, 'owner')->active()->create(['api_key' => 'sk-fake-ada']);
        AiCredential::factory()->for($grace, 'owner')->active()->create(['api_key' => 'sk-fake-grace']);

        $resolver = app(AiConnectionResolver::class);

        $this->assertSame('sk-fake-ada', $resolver->forUser($ada)->apiKey);
        $this->assertSame('sk-fake-grace', $resolver->forUser($grace)->apiKey);
    }

    #[Test]
    public function activating_a_credential_deactivates_the_previous_one(): void
    {
        $user = User::factory()->create();
        $first = AiCredential::factory()->for($user, 'owner')->active()->create();
        $second = AiCredential::factory()->for($user, 'owner')->verified()->create();

        app(ActivateAiCredential::class)->handle($second);

        $this->assertFalse($first->fresh()?->is_active);
        $this->assertTrue($second->fresh()?->is_active);
        $this->assertSame(1, AiCredential::query()->forUser($user->id)->active()->count());
    }

    #[Test]
    public function activating_an_already_active_credential_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $credential = AiCredential::factory()->for($user, 'owner')->active()->create();

        app(ActivateAiCredential::class)->handle($credential);

        $this->assertTrue($credential->fresh()?->is_active);
        $this->assertSame(1, AiCredential::query()->forUser($user->id)->active()->count());
    }
}
