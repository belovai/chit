<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_merchant_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/merchants', ['name' => 'OMV']);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'OMV');
        $this->assertDatabaseHas('merchants', ['owner_id' => $user->id, 'name' => 'OMV']);
    }

    #[Test]
    public function it_rejects_a_duplicate_name_case_insensitively_for_the_same_owner(): void
    {
        $user = User::factory()->create();
        Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/merchants', ['name' => 'omv']);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.name.0', 'merchant.duplicate_name');
    }

    #[Test]
    public function it_allows_the_same_name_for_a_different_owner(): void
    {
        Merchant::factory()->create(['name' => 'OMV']);
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/merchants', ['name' => 'OMV']);

        $response->assertCreated();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->postJson('/api/merchants', ['name' => 'OMV']);

        $response->assertUnauthorized();
    }
}
