<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateAiCredentialCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.fake', true);
        FakeAiProvider::reset();
    }

    #[Test]
    public function it_stores_a_credential_for_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('ai:credential:create', [
            'email' => 'ada@example.com',
            '--provider' => 'fake',
            '--model' => 'fake-model',
        ])
            ->expectsQuestion('API key', 'sk-fake-abcd')
            ->assertExitCode(0);

        $credential = AiCredential::query()->sole();

        $this->assertSame($user->id, $credential->owner_id);
        $this->assertSame('sk-fake-abcd', $credential->api_key);
        $this->assertTrue($credential->is_active);
    }

    #[Test]
    public function an_unknown_email_fails_without_creating_anything(): void
    {
        $this->artisan('ai:credential:create', [
            'email' => 'nobody@example.com',
            '--provider' => 'fake',
            '--model' => 'fake-model',
        ])->assertExitCode(1);

        $this->assertSame(0, AiCredential::query()->count());
    }

    #[Test]
    public function a_key_the_provider_rejects_fails_the_command(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);
        FakeAiProvider::willFailVerification('authentication_error: invalid x-api-key');

        $this->artisan('ai:credential:create', [
            'email' => 'ada@example.com',
            '--provider' => 'fake',
            '--model' => 'fake-model',
        ])
            ->expectsQuestion('API key', 'sk-fake-bad')
            ->assertExitCode(1);

        $this->assertSame(0, AiCredential::query()->count());
    }
}
