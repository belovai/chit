<?php

declare(strict_types=1);

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SuggestProductCandidatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_suggests_a_near_identical_product_above_the_threshold(): void
    {
        $user = User::factory()->create();
        Product::factory()->for($user, 'owner')->create(['name' => 'Riska tej 2.8% UHT 1L']);
        Product::factory()->for($user, 'owner')->create(['name' => 'Kenyer feher']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products/suggest?'.http_build_query(['query' => 'Riska tej 2.8%']));

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('product.name');

        $this->assertTrue($names->contains('Riska tej 2.8% UHT 1L'));
        $this->assertFalse($names->contains('Kenyer feher'));
    }

    #[Test]
    public function it_only_suggests_the_authenticated_users_own_products(): void
    {
        $otherUser = User::factory()->create();
        Product::factory()->for($otherUser, 'owner')->create(['name' => 'Riska tej 2.8%']);
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products/suggest?'.http_build_query(['query' => 'Riska tej 2.8%']));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_returns_no_candidates_below_the_similarity_threshold(): void
    {
        $user = User::factory()->create();
        Product::factory()->for($user, 'owner')->create(['name' => 'Kenyer feher']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products/suggest?'.http_build_query(['query' => 'Totally Unrelated Name']));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_requires_a_query_parameter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products/suggest');

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.query.0', 'required');
    }
}
