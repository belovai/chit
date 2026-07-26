<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantLocationStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_physical_location_for_the_owners_merchant(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => false,
                'address' => 'Szeged, Kalvaria ter 3.',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_online', false);
        $response->assertJsonPath('data.address', 'Szeged, Kalvaria ter 3.');
        $this->assertDatabaseHas('merchant_locations', [
            'merchant_id' => $merchant->id,
            'address' => 'Szeged, Kalvaria ter 3.',
        ]);
    }

    #[Test]
    public function it_creates_an_online_location_without_an_address(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_online', true);
        $response->assertJsonPath('data.address', null);
    }

    #[Test]
    public function it_requires_an_address_when_not_online(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => false,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.address.0', 'required');
    }

    #[Test]
    public function it_rejects_an_address_when_online(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => true,
                'address' => 'Szeged, Kalvaria ter 3.',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.address.0', 'prohibited');
    }

    #[Test]
    public function it_returns_404_for_another_users_merchant(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => false,
                'address' => 'Szeged, Kalvaria ter 3.',
            ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $merchant = Merchant::factory()->create();

        $response = $this->postJson("/api/merchants/{$merchant->hash_id}/locations", [
            'is_online' => false,
            'address' => 'Szeged, Kalvaria ter 3.',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_creates_a_physical_location_with_coordinates(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => false,
                'address' => 'Szeged, Kalvaria ter 3.',
                'latitude' => 46.253,
                'longitude' => 20.1414,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.latitude', 46.253);
        $response->assertJsonPath('data.longitude', 20.1414);
        $this->assertDatabaseHas('merchant_locations', [
            'merchant_id' => $merchant->id,
            'latitude' => 46.253,
            'longitude' => 20.1414,
        ]);
    }

    #[Test]
    public function it_rejects_coordinates_when_online(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => true,
                'latitude' => 46.253,
                'longitude' => 20.1414,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.latitude.0', 'prohibited');
        $response->assertJsonPath('errors.longitude.0', 'prohibited');
    }

    #[Test]
    public function it_rejects_a_second_online_location_for_the_same_merchant(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        MerchantLocation::factory()->for($merchant)->create(['is_online' => true, 'address' => null]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => true,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.is_online.0', 'merchants.onlineLocationExists');
    }

    #[Test]
    public function it_rejects_out_of_range_coordinates(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/merchants/{$merchant->hash_id}/locations", [
                'is_online' => false,
                'address' => 'Szeged, Kalvaria ter 3.',
                'latitude' => 200,
                'longitude' => 20.1414,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.latitude.0', 'between');
    }
}
