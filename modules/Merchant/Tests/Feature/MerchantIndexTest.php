<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_the_authenticated_users_merchants(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV']);
        Merchant::factory()->for($otherUser, 'owner')->create(['name' => 'Tesco']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/merchants');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'OMV');
    }

    #[Test]
    public function it_lists_the_owners_merchants_with_a_location_count(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        MerchantLocation::factory()->for($merchant)->count(2)->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/merchants');

        $response->assertOk();
        $response->assertJsonPath('data.0.locations_count', 2);
    }

    #[Test]
    public function it_does_not_list_another_users_merchants(): void
    {
        $owner = User::factory()->create();
        Merchant::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/merchants');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}
