<?php

declare(strict_types=1);

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductShowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_owners_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for($user, 'owner')->create(['name' => 'Riska tej 2.8%']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/products/{$product->hash_id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Riska tej 2.8%');
    }

    #[Test]
    public function it_returns_404_for_another_users_product(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/products/{$product->hash_id}");

        $response->assertNotFound();
    }
}
