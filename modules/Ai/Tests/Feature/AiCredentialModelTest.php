<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Models\AiCredential;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AiCredentialModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_api_key_is_encrypted_at_rest(): void
    {
        $credential = AiCredential::factory()->create(['api_key' => 'sk-ant-secret-value']);

        $raw = DB::table('ai_credentials')->where('id', $credential->id)->value('api_key');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk-ant-secret-value', $raw);
        $this->assertSame('sk-ant-secret-value', $credential->fresh()?->api_key);
    }

    #[Test]
    public function the_fingerprint_is_a_stable_sha256_of_the_key(): void
    {
        $this->assertSame(
            hash('sha256', 'sk-ant-abc'),
            AiCredential::fingerprint('sk-ant-abc'),
        );
        $this->assertSame('bcd1', AiCredential::lastFour('sk-ant-abcd1'));
    }

    #[Test]
    public function the_same_key_cannot_be_stored_twice_for_one_user_and_provider(): void
    {
        $user = User::factory()->create();
        $fingerprint = AiCredential::fingerprint('sk-ant-abc');

        AiCredential::factory()->for($user, 'owner')->create([
            'provider' => 'fake',
            'key_fingerprint' => $fingerprint,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        AiCredential::factory()->for($user, 'owner')->create([
            'provider' => 'fake',
            'key_fingerprint' => $fingerprint,
        ]);
    }

    #[Test]
    public function two_users_may_hold_the_same_key(): void
    {
        $fingerprint = AiCredential::fingerprint('sk-ant-shared');

        AiCredential::factory()->for(User::factory(), 'owner')->create(['key_fingerprint' => $fingerprint]);
        AiCredential::factory()->for(User::factory(), 'owner')->create(['key_fingerprint' => $fingerprint]);

        $this->assertSame(2, AiCredential::query()->count());
    }

    #[Test]
    public function a_user_cannot_have_two_active_credentials(): void
    {
        $user = User::factory()->create();
        AiCredential::factory()->for($user, 'owner')->active()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        AiCredential::factory()->for($user, 'owner')->active()->create();
    }

    #[Test]
    public function only_a_verified_status_is_usable(): void
    {
        $this->assertTrue(CredentialStatus::Verified->isUsable());
        $this->assertFalse(CredentialStatus::Pending->isUsable());
        $this->assertFalse(CredentialStatus::Failing->isUsable());
        $this->assertFalse(CredentialStatus::Disabled->isUsable());
    }

    /**
     * Account deletion is two-phase: the request soft-deletes, and
     * PurgeUserData force-deletes later. Only the second phase fires the
     * database cascade, so that is what this asserts.
     */
    #[Test]
    public function force_deleting_a_user_deletes_their_credentials(): void
    {
        $user = User::factory()->create();
        AiCredential::factory()->for($user, 'owner')->create();

        $user->delete();

        $this->assertSame(1, AiCredential::query()->count(), 'a soft delete leaves the row');

        $user->forceDelete();

        $this->assertSame(0, AiCredential::query()->count());
    }
}
