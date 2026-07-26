<?php

declare(strict_types=1);

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_product_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', ['name' => 'Riska tej 2.8%']);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Riska tej 2.8%');
        $this->assertDatabaseHas('products', ['owner_id' => $user->id, 'name' => 'Riska tej 2.8%']);
    }

    #[Test]
    public function it_rejects_a_duplicate_name_case_insensitively_for_the_same_owner(): void
    {
        $user = User::factory()->create();
        Product::factory()->for($user, 'owner')->create(['name' => 'Riska tej 2.8%']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', ['name' => 'riska tej 2.8%']);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.name.0', 'product.duplicate_name');
    }

    #[Test]
    public function it_allows_the_same_name_for_a_different_owner(): void
    {
        Product::factory()->create(['name' => 'Riska tej 2.8%']);
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', ['name' => 'Riska tej 2.8%']);

        $response->assertCreated();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->postJson('/api/products', ['name' => 'Riska tej 2.8%']);

        $response->assertUnauthorized();
    }
}
