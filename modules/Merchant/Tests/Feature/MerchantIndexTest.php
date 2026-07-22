<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
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
}
