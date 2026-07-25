<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SuggestMerchantCandidatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_suggests_a_near_identical_merchant_above_the_threshold(): void
    {
        $user = User::factory()->create();
        Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV Hodmezovasarhely 2']);
        Merchant::factory()->for($user, 'owner')->create(['name' => 'Lidl Aruhaz']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/merchants/suggest?'.http_build_query(['query' => 'OMV Hodmezovasarhely']));

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('merchant.name');

        $this->assertTrue($names->contains('OMV Hodmezovasarhely 2'));
        $this->assertFalse($names->contains('Lidl Aruhaz'));
    }

    #[Test]
    public function it_only_suggests_the_authenticated_users_own_merchants(): void
    {
        $otherUser = User::factory()->create();
        Merchant::factory()->for($otherUser, 'owner')->create(['name' => 'OMV']);
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/merchants/suggest?'.http_build_query(['query' => 'OMV']));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_returns_no_candidates_below_the_similarity_threshold(): void
    {
        $user = User::factory()->create();
        Merchant::factory()->for($user, 'owner')->create(['name' => 'Lidl Aruhaz']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/merchants/suggest?'.http_build_query(['query' => 'Totally Unrelated Name']));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_requires_a_query_parameter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/merchants/suggest');

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.query.0', 'required');
    }
}
