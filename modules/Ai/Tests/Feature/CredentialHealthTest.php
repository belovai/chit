<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Events\AiCredentialDisabled;
use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Services\AiClientFactory;
use Modules\Ai\Services\AiConnectionResolver;
use Modules\Ai\Services\CredentialHealth;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\TextPart;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CredentialHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.fake', true);
        config()->set('ai.auth_failure_threshold', 3);
        FakeAiProvider::reset();
    }

    #[Test]
    public function an_auth_failure_marks_the_credential_failing(): void
    {
        $credential = AiCredential::factory()->active()->create();

        app(CredentialHealth::class)->failed(
            $credential->id,
            'authentication_error: invalid x-api-key',
            isAuthFailure: true,
        );

        $fresh = $credential->fresh();

        $this->assertSame(CredentialStatus::Failing, $fresh?->status);
        $this->assertSame(1, $fresh?->failure_count);
        $this->assertSame('authentication_error: invalid x-api-key', $fresh?->last_error);
    }

    #[Test]
    public function reaching_the_threshold_disables_and_deactivates_the_credential(): void
    {
        Event::fake([AiCredentialDisabled::class]);

        $credential = AiCredential::factory()->active()->create();

        foreach (range(1, 3) as $ignored) {
            app(CredentialHealth::class)->failed($credential->id, 'authentication_error: nope', true);
        }

        $fresh = $credential->fresh();

        $this->assertSame(CredentialStatus::Disabled, $fresh?->status);
        $this->assertFalse($fresh?->is_active);
        $this->assertSame(3, $fresh?->failure_count);
        Event::assertDispatched(AiCredentialDisabled::class);
    }

    #[Test]
    public function a_non_auth_failure_does_not_count_toward_the_threshold(): void
    {
        $credential = AiCredential::factory()->active()->create();

        app(CredentialHealth::class)->failed($credential->id, 'overloaded', isAuthFailure: false);

        $fresh = $credential->fresh();

        $this->assertSame(CredentialStatus::Verified, $fresh?->status);
        $this->assertSame(0, $fresh?->failure_count);
        $this->assertSame('overloaded', $fresh?->last_error);
    }

    #[Test]
    public function a_success_clears_the_failure_count(): void
    {
        $credential = AiCredential::factory()->active()->create([
            'status' => CredentialStatus::Failing,
            'failure_count' => 2,
            'last_error' => 'authentication_error: nope',
        ]);

        app(CredentialHealth::class)->succeeded($credential->id);

        $fresh = $credential->fresh();

        $this->assertSame(CredentialStatus::Verified, $fresh?->status);
        $this->assertSame(0, $fresh?->failure_count);
        $this->assertNull($fresh?->last_error);
    }

    #[Test]
    public function an_auth_failure_raised_through_the_client_is_recorded(): void
    {
        FakeAiProvider::willFail(AiException::permanent('authentication_error: invalid x-api-key'));

        $credential = AiCredential::factory()->active()->create();
        $connection = app(AiConnectionResolver::class)->forCredential($credential);

        try {
            app(AiClientFactory::class)->for($connection)
                ->complete(new AiRequest('system', [new TextPart('hi')]));
        } catch (AiException) {
            // expected
        }

        $this->assertSame(CredentialStatus::Failing, $credential->fresh()?->status);
    }
}
