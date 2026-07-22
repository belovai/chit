<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renames_the_owners_merchant(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV Hmvhely 2']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchants/{$merchant->hash_id}", ['name' => 'OMV']);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'OMV');
        $this->assertSame('OMV', $merchant->fresh()->name);
    }

    #[Test]
    public function it_rejects_renaming_to_a_name_that_collides_with_another_of_the_owners_merchants(): void
    {
        $user = User::factory()->create();
        Merchant::factory()->for($user, 'owner')->create(['name' => 'Lidl']);
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchants/{$merchant->hash_id}", ['name' => 'lidl']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    #[Test]
    public function it_returns_404_when_renaming_another_users_merchant(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/merchants/{$merchant->hash_id}", ['name' => 'New Name']);

        $response->assertNotFound();
    }
}
