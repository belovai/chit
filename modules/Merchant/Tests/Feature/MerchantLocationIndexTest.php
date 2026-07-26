<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantLocationIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_the_given_merchants_locations(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $otherMerchant = Merchant::factory()->for($user, 'owner')->create();
        MerchantLocation::factory()->for($merchant)->create(['address' => 'Szeged, Kalvaria ter 3.']);
        MerchantLocation::factory()->for($otherMerchant)->create(['address' => 'Budapest, Vaci utca 1.']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}/locations");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.address', 'Szeged, Kalvaria ter 3.');
    }

    #[Test]
    public function it_returns_404_for_another_users_merchant(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}/locations");

        $response->assertNotFound();
    }
}
