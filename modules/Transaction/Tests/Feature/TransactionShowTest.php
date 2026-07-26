<?php

declare(strict_types=1);

namespace Modules\Transaction\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Transaction\Models\Transaction;
use Modules\Transaction\Models\TransactionItem;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TransactionShowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_owners_transaction_with_items(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user, 'owner')->create();
        TransactionItem::factory()->for($transaction)->create(['description' => 'Riska tej']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/transactions/{$transaction->hash_id}");

        $response->assertOk();
        $response->assertJsonPath('data.items.0.description', 'Riska tej');
    }

    #[Test]
    public function it_returns_404_for_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $transaction = Transaction::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/transactions/{$transaction->hash_id}");

        $response->assertNotFound();
    }
}
