<?php

declare(strict_types=1);

namespace Modules\Transaction\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Transaction\Models\Transaction;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TransactionDestroyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_soft_deletes_the_owners_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/transactions/{$transaction->hash_id}");

        $response->assertOk();
        $this->assertSoftDeleted($transaction);
    }

    #[Test]
    public function it_returns_404_when_deleting_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $transaction = Transaction::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/transactions/{$transaction->hash_id}");

        $response->assertNotFound();
    }
}
