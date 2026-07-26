<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantLocationDestroyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_soft_deletes_the_owners_location(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $location = MerchantLocation::factory()->for($merchant)->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/merchant-locations/{$location->hash_id}");

        $response->assertOk();
        $this->assertSoftDeleted($location);
    }

    #[Test]
    public function it_returns_404_when_deleting_another_users_location(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->for($owner, 'owner')->create();
        $location = MerchantLocation::factory()->for($merchant)->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/merchant-locations/{$location->hash_id}");

        $response->assertNotFound();
    }
}
