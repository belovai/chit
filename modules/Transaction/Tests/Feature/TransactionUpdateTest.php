<?php

declare(strict_types=1);

namespace Modules\Transaction\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Transaction\Models\Transaction;
use Modules\Transaction\Models\TransactionItem;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TransactionUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_replaces_all_items_on_update(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $transaction = Transaction::factory()->for($user, 'owner')->for($merchant)->create();
        TransactionItem::factory()->for($transaction)->create(['description' => 'Old item']);
        TransactionItem::factory()->for($transaction)->create(['description' => 'Another old item']);
        $token = $user->createToken('api')->plainTextToken;

        $payload = [
            'merchant_id' => $merchant->hash_id,
            'location_id' => null,
            'currency' => 'HUF',
            'source' => 'manual',
            'payment_method' => 'cash',
            'discount_amount' => null,
            'total_amount' => 500,
            'occurred_at' => '2026-07-26',
            'items' => [
                [
                    'product_id' => null,
                    'description' => 'New item',
                    'quantity' => 1,
                    'unit' => 'db',
                    'unit_price' => 500,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/transactions/{$transaction->hash_id}", $payload);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.description', 'New item');
        $this->assertDatabaseCount('transaction_items', 1);
        $this->assertDatabaseMissing('transaction_items', ['description' => 'Old item']);
    }

    #[Test]
    public function it_returns_404_when_updating_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->for($owner, 'owner')->create();
        $transaction = Transaction::factory()->for($owner, 'owner')->for($merchant)->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/transactions/{$transaction->hash_id}", [
                'merchant_id' => $merchant->hash_id,
                'location_id' => null,
                'currency' => 'HUF',
                'source' => 'manual',
                'payment_method' => 'cash',
                'discount_amount' => null,
                'total_amount' => 500,
                'occurred_at' => '2026-07-26',
                'items' => [
                    ['product_id' => null, 'description' => 'Item', 'quantity' => 1, 'unit' => 'db', 'unit_price' => 500],
                ],
            ]);

        $response->assertNotFound();
    }
}
