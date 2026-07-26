<?php

declare(strict_types=1);

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renames_the_owners_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for($user, 'owner')->create(['name' => 'Riska tej']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/products/{$product->hash_id}", ['name' => 'Riska tej 2.8%']);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Riska tej 2.8%');
        $this->assertSame('Riska tej 2.8%', $product->fresh()->name);
    }

    #[Test]
    public function it_rejects_renaming_to_a_name_that_collides_with_another_of_the_owners_products(): void
    {
        $user = User::factory()->create();
        Product::factory()->for($user, 'owner')->create(['name' => 'Mizo tej']);
        $product = Product::factory()->for($user, 'owner')->create(['name' => 'Riska tej']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/products/{$product->hash_id}", ['name' => 'mizo tej']);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.name.0', 'product.duplicate_name');
    }

    #[Test]
    public function it_returns_404_when_renaming_another_users_product(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/products/{$product->hash_id}", ['name' => 'New Name']);

        $response->assertNotFound();
    }
}
