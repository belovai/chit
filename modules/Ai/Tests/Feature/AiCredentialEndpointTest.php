<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AiCredentialEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.fake', true);
        FakeAiProvider::reset();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'provider' => 'fake',
            'label' => 'My key',
            'api_key' => 'sk-fake-1234',
            'model' => 'fake-model',
            'settings' => ['max_tokens' => 4000, 'effort' => 'low'],
            ...$overrides,
        ];
    }

    #[Test]
    public function a_user_can_store_a_credential_and_it_becomes_active(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/ai/credentials', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('data.provider', 'fake');
        $response->assertJsonPath('data.status', 'verified');
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.masked_key', '••••1234');

        $this->assertSame(1, AiCredential::query()->forUser($user->id)->count());
    }

    #[Test]
    public function the_response_never_contains_the_api_key(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/ai/credentials', $this->payload());

        $body = $response->getContent();

        $this->assertIsString($body);
        $this->assertStringNotContainsString('sk-fake-1234', $body);
        $this->assertArrayNotHasKey('api_key', $response->json('data'));
        $this->assertArrayNotHasKey('key_fingerprint', $response->json('data'));
    }

    #[Test]
    public function a_key_the_provider_rejects_is_not_stored(): void
    {
        FakeAiProvider::willFailVerification('authentication_error: invalid x-api-key');

        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/ai/credentials', $this->payload());

        $response->assertStatus(422);
        $this->assertSame(0, AiCredential::query()->count());
    }

    #[Test]
    public function a_second_credential_does_not_steal_the_active_flag(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/ai/credentials', $this->payload())->assertCreated();
        $second = $this->withToken($token)
            ->postJson('/api/ai/credentials', $this->payload(['api_key' => 'sk-fake-5678']))
            ->assertCreated();

        $this->assertFalse($second->json('data.is_active'));
        $this->assertSame(1, AiCredential::query()->forUser($user->id)->active()->count());
    }

    #[Test]
    public function an_unknown_model_is_rejected_by_validation(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/ai/credentials', $this->payload(['model' => 'not-a-model']))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('model');
    }

    #[Test]
    public function settings_outside_the_declared_range_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/ai/credentials', $this->payload([
                'settings' => ['max_tokens' => 999_999, 'effort' => 'low'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('settings');
    }

    #[Test]
    public function an_unknown_setting_key_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/ai/credentials', $this->payload([
                'settings' => ['max_tokens' => 4000, 'effort' => 'low', 'temperature' => 2],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('settings');
    }

    #[Test]
    public function the_same_key_cannot_be_added_twice(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/ai/credentials', $this->payload())->assertCreated();

        $this->withToken($token)->postJson('/api/ai/credentials', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('api_key');
    }

    #[Test]
    public function a_user_only_sees_their_own_credentials(): void
    {
        $ada = User::factory()->create();
        AiCredential::factory()->for($ada, 'owner')->active()->create();
        AiCredential::factory()->for(User::factory(), 'owner')->active()->create();

        $response = $this->withToken($this->tokenFor($ada))->getJson('/api/ai/credentials');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function a_user_cannot_touch_another_users_credential(): void
    {
        $ada = User::factory()->create();
        $others = AiCredential::factory()->for(User::factory(), 'owner')->active()->create();

        $token = $this->tokenFor($ada);

        $this->withToken($token)->patchJson("/api/ai/credentials/{$others->hash_id}", ['label' => 'mine now'])
            ->assertForbidden();
        $this->withToken($token)->postJson("/api/ai/credentials/{$others->hash_id}/activate")
            ->assertForbidden();
        $this->withToken($token)->deleteJson("/api/ai/credentials/{$others->hash_id}")
            ->assertForbidden();
    }

    #[Test]
    public function activating_a_credential_switches_the_active_one(): void
    {
        $user = User::factory()->create();
        $first = AiCredential::factory()->for($user, 'owner')->active()->create();
        $second = AiCredential::factory()->for($user, 'owner')->verified()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/ai/credentials/{$second->hash_id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertFalse($first->fresh()?->is_active);
    }

    #[Test]
    public function updating_the_label_alone_does_not_require_a_key(): void
    {
        $user = User::factory()->create();
        $credential = AiCredential::factory()->for($user, 'owner')->active()->create();

        $this->withToken($this->tokenFor($user))
            ->patchJson("/api/ai/credentials/{$credential->hash_id}", ['label' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Renamed');
    }

    #[Test]
    public function deleting_the_active_credential_promotes_another_verified_one(): void
    {
        $user = User::factory()->create();
        $active = AiCredential::factory()->for($user, 'owner')->active()->create();
        $spare = AiCredential::factory()->for($user, 'owner')->verified()->create();

        $this->withToken($this->tokenFor($user))
            ->deleteJson("/api/ai/credentials/{$active->hash_id}")
            ->assertOk();

        $this->assertTrue($spare->fresh()?->is_active);
    }

    #[Test]
    public function it_returns_coded_validation_messages(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/ai/credentials', []);

        $response->assertStatus(422);
        $this->assertSame(['required'], $response->json('errors.label'));
        $this->assertSame(['required'], $response->json('errors.api_key'));
    }

    #[Test]
    public function re_verifying_a_disabled_credential_restores_it(): void
    {
        $user = User::factory()->create();
        $credential = AiCredential::factory()->for($user, 'owner')->disabled()->create(['model' => 'fake-model']);

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/ai/credentials/{$credential->hash_id}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertSame(0, $credential->fresh()?->failure_count);
        $this->assertSame(CredentialStatus::Verified, $credential->fresh()?->status);
    }
}
