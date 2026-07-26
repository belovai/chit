<?php

declare(strict_types=1);

namespace Modules\Transaction\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TransactionStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_transaction_with_items_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $location = MerchantLocation::factory()->for($merchant)->create();
        $product = Product::factory()->for($user, 'owner')->create(['name' => 'Riska tej 2.8%']);
        $token = $user->createToken('api')->plainTextToken;

        $payload = [
            'merchant_id' => $merchant->hash_id,
            'location_id' => $location->hash_id,
            'currency' => 'HUF',
            'source' => 'manual',
            'payment_method' => 'card',
            'discount_amount' => 200,
            'total_amount' => 1800,
            'occurred_at' => '2026-07-26',
            'items' => [
                [
                    'product_id' => $product->hash_id,
                    'description' => 'Riska tej 2.8% UHT 1L',
                    'quantity' => 2,
                    'unit' => 'db',
                    'unit_price' => 900,
                ],
                [
                    'product_id' => null,
                    'description' => 'Kenyer feher',
                    'quantity' => 1,
                    'unit' => 'db',
                    'unit_price' => 100,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/transactions', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.merchant.hash_id', $merchant->hash_id);
        $response->assertJsonPath('data.location.hash_id', $location->hash_id);
        $response->assertJsonPath('data.total_amount', '1800.00');
        $response->assertJsonCount(2, 'data.items');
        $response->assertJsonPath('data.items.0.product.hash_id', $product->hash_id);
        $response->assertJsonPath('data.items.1.product', null);

        $this->assertDatabaseHas('transactions', ['owner_id' => $user->id, 'merchant_id' => $merchant->id]);
        $this->assertDatabaseCount('transaction_items', 2);
    }

    #[Test]
    public function it_rejects_a_merchant_that_does_not_belong_to_the_owner(): void
    {
        $otherOwner = User::factory()->create();
        $merchant = Merchant::factory()->for($otherOwner, 'owner')->create();
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/transactions', $this->validPayload($merchant->hash_id));

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.merchant_id.0', 'transaction.not_found');
    }

    #[Test]
    public function it_rejects_a_product_that_does_not_belong_to_the_owner(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $otherOwner = User::factory()->create();
        $product = Product::factory()->for($otherOwner, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $payload = $this->validPayload($merchant->hash_id);
        $payload['items'][0]['product_id'] = $product->hash_id;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/transactions', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items.0.product_id' => 'transaction.not_found']);
    }

    #[Test]
    public function it_rejects_a_location_that_does_not_belong_to_the_given_merchant(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $otherMerchant = Merchant::factory()->for($user, 'owner')->create();
        $location = MerchantLocation::factory()->for($otherMerchant)->create();
        $token = $user->createToken('api')->plainTextToken;

        $payload = $this->validPayload($merchant->hash_id);
        $payload['location_id'] = $location->hash_id;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/transactions', $payload);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.location_id.0', 'transaction.location_not_found');
    }

    #[Test]
    public function it_requires_at_least_one_item(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $payload = $this->validPayload($merchant->hash_id);
        $payload['items'] = [];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/transactions', $payload);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.items.0', 'required');
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->postJson('/api/transactions', $this->validPayload('does-not-matter'));

        $response->assertUnauthorized();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $merchantHashId): array
    {
        return [
            'merchant_id' => $merchantHashId,
            'location_id' => null,
            'currency' => 'HUF',
            'source' => 'manual',
            'payment_method' => 'card',
            'discount_amount' => null,
            'total_amount' => 900,
            'occurred_at' => '2026-07-26',
            'items' => [
                [
                    'product_id' => null,
                    'description' => 'Kenyer feher',
                    'quantity' => 1,
                    'unit' => 'db',
                    'unit_price' => 900,
                ],
            ],
        ];
    }
}
