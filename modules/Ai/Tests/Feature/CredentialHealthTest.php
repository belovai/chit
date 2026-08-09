<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Enums\FailureKind;
use Modules\Ai\Events\AiCredentialDisabled;
use Modules\Ai\Events\AiCredentialSuspended;
use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Exceptions\NoActiveAiCredentialException;
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
            FailureKind::AuthFailure,
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
            app(CredentialHealth::class)->failed($credential->id, 'authentication_error: nope', FailureKind::AuthFailure);
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

        app(CredentialHealth::class)->failed($credential->id, 'overloaded', FailureKind::Transient);

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
        FakeAiProvider::willFail(AiException::authFailure('authentication_error: invalid x-api-key'));

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

    #[Test]
    public function an_unusable_account_suspends_without_marking_the_key(): void
    {
        Event::fake([AiCredentialSuspended::class, AiCredentialDisabled::class]);

        $credential = AiCredential::factory()->active()->create();

        app(CredentialHealth::class)->failed(
            $credential->id,
            'billing_error: credit balance is too low',
            FailureKind::Unusable,
        );

        $fresh = $credential->fresh();

        $this->assertSame(CredentialStatus::Suspended, $fresh?->status);
        // The key is not at fault: nothing counts against it, nothing is turned off.
        $this->assertSame(0, $fresh?->failure_count);
        $this->assertTrue($fresh?->is_active);
        $this->assertSame('billing_error: credit balance is too low', $fresh?->last_error);

        Event::assertDispatched(AiCredentialSuspended::class);
        Event::assertNotDispatched(AiCredentialDisabled::class);
    }

    #[Test]
    public function repeated_unusable_failures_never_reach_the_disable_threshold(): void
    {
        $credential = AiCredential::factory()->active()->create();

        foreach (range(1, 5) as $ignored) {
            app(CredentialHealth::class)->failed($credential->id, 'billing_error: nope', FailureKind::Unusable);
        }

        $fresh = $credential->fresh();

        $this->assertSame(CredentialStatus::Suspended, $fresh?->status);
        $this->assertSame(0, $fresh?->failure_count);
        $this->assertTrue($fresh?->is_active);
    }

    #[Test]
    public function a_suspended_credential_announces_itself_once(): void
    {
        Event::fake([AiCredentialSuspended::class]);

        $credential = AiCredential::factory()->suspended()->create();

        app(CredentialHealth::class)->failed($credential->id, 'billing_error: still broke', FailureKind::Unusable);

        // Already suspended: the user has been told, so a second job hitting the
        // same wall must not queue another notice.
        Event::assertNotDispatched(AiCredentialSuspended::class);
    }

    #[Test]
    public function a_suspended_credential_is_held_out_of_use(): void
    {
        $credential = AiCredential::factory()->suspended()->create();

        $this->expectException(NoActiveAiCredentialException::class);

        app(AiConnectionResolver::class)->forCredential($credential);
    }

    #[Test]
    public function an_unusable_account_raised_through_the_client_suspends_the_credential(): void
    {
        FakeAiProvider::willFail(AiException::unusable('billing_error: credit balance is too low'));

        $credential = AiCredential::factory()->active()->create();
        $connection = app(AiConnectionResolver::class)->forCredential($credential);

        try {
            app(AiClientFactory::class)->for($connection)
                ->complete(new AiRequest('system', [new TextPart('hi')]));
        } catch (AiException) {
            // expected
        }

        $fresh = $credential->fresh();

        $this->assertSame(CredentialStatus::Suspended, $fresh?->status);
        $this->assertSame(0, $fresh?->failure_count);
    }
}
