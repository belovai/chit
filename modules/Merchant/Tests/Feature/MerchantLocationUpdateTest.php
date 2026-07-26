<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantLocationUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_the_address_of_the_owners_location(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $location = MerchantLocation::factory()->for($merchant)->create(['address' => 'Old address']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchant-locations/{$location->hash_id}", [
                'is_online' => false,
                'address' => 'New address',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.address', 'New address');
        $this->assertSame('New address', $location->fresh()->address);
    }

    #[Test]
    public function it_switches_a_location_to_online_and_clears_the_address(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $location = MerchantLocation::factory()->for($merchant)->create(['address' => 'Old address']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchant-locations/{$location->hash_id}", [
                'is_online' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_online', true);
        $this->assertNull($location->fresh()->address);
    }

    #[Test]
    public function it_rejects_switching_to_online_when_another_location_is_already_online(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        MerchantLocation::factory()->for($merchant)->create(['is_online' => true, 'address' => null]);
        $location = MerchantLocation::factory()->for($merchant)->create(['address' => 'Old address']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchant-locations/{$location->hash_id}", [
                'is_online' => true,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.is_online.0', 'merchants.onlineLocationExists');
    }

    #[Test]
    public function it_allows_resaving_the_location_that_is_already_online(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $location = MerchantLocation::factory()->for($merchant)->create(['is_online' => true, 'address' => null]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchant-locations/{$location->hash_id}", [
                'is_online' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_online', true);
    }

    #[Test]
    public function it_returns_404_when_updating_another_users_location(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->for($owner, 'owner')->create();
        $location = MerchantLocation::factory()->for($merchant)->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchant-locations/{$location->hash_id}", [
                'is_online' => false,
                'address' => 'New address',
            ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_updates_the_coordinates_of_the_owners_location(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $location = MerchantLocation::factory()->for($merchant)->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchant-locations/{$location->hash_id}", [
                'is_online' => false,
                'address' => 'New address',
                'latitude' => 46.253,
                'longitude' => 20.1414,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.latitude', 46.253);
        $response->assertJsonPath('data.longitude', 20.1414);
    }
}
