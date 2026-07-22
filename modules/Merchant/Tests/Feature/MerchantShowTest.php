<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantShowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_owners_merchant(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'OMV');
    }

    #[Test]
    public function it_returns_404_for_another_users_merchant(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}");

        $response->assertNotFound();
    }
}
