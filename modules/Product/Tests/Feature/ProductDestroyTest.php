<?php

declare(strict_types=1);

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductDestroyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_soft_deletes_the_owners_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/products/{$product->hash_id}");

        $response->assertOk();
        $this->assertSoftDeleted($product);
    }

    #[Test]
    public function it_returns_404_when_deleting_another_users_product(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/products/{$product->hash_id}");

        $response->assertNotFound();
    }
}
