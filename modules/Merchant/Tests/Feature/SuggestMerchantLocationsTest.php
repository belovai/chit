<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SuggestMerchantLocationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_accepts_the_branch_matching_the_given_address(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV']);
        $balaton = MerchantLocation::factory()->for($merchant)->create([
            'address' => '8175 Balatonfűzfő, Árpád út 1',
        ]);
        MerchantLocation::factory()->for($merchant)->create([
            'address' => '6800 Hódmezővásárhely, Kutasi út 17.',
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}/locations/suggest?address=".urlencode('8175 Balatonfűzfő, Árpád út 1'));

        $response->assertOk();
        $response->assertJsonPath('data.accepted_hash_id', $balaton->hash_id);
        $response->assertJsonPath('data.ambiguous', false);
        $response->assertJsonCount(2, 'data.candidates');
        $response->assertJsonPath('data.candidates.0.hash_id', $balaton->hash_id);
    }

    #[Test]
    public function without_an_address_it_lists_every_branch_unscored(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '8175 Balatonfuzfo, Arpad ut 1']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '6800 Hodmezovasarhely, Kutasi ut 17.']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}/locations/suggest");

        $response->assertOk();
        $response->assertJsonCount(2, 'data.candidates');
        $response->assertJsonPath('data.accepted_hash_id', null);
        $response->assertJsonPath('data.candidates.0.score', null);
    }

    #[Test]
    public function an_unknown_address_accepts_nothing_but_still_lists_the_branches(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '8175 Balatonfuzfo, Arpad ut 1']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}/locations/suggest?address=".urlencode('1052 Budapest, Deák Ferenc tér 3.'));

        $response->assertOk();
        $response->assertJsonPath('data.accepted_hash_id', null);
        $response->assertJsonCount(1, 'data.candidates');
    }

    #[Test]
    public function it_returns_404_for_another_users_merchant(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->for($owner, 'owner')->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}/locations/suggest");

        $response->assertNotFound();
    }

    #[Test]
    public function it_rejects_an_over_long_address(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/merchants/{$merchant->hash_id}/locations/suggest?address=".str_repeat('a', 501));

        $response->assertStatus(422);
    }
}
