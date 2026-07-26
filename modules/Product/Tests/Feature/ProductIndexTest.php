<?php

declare(strict_types=1);

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_the_authenticated_users_products(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Product::factory()->for($user, 'owner')->create(['name' => 'Riska tej 2.8%']);
        Product::factory()->for($otherUser, 'owner')->create(['name' => 'Mizo tej']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Riska tej 2.8%');
    }

    #[Test]
    public function it_does_not_list_another_users_products(): void
    {
        $owner = User::factory()->create();
        Product::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}
