<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Merchant\Services\LocationMatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LocationMatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_accepts_the_same_branch_spelled_differently(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'SPAR']);
        $location = MerchantLocation::factory()->for($merchant)->create([
            'address' => '6723 Szeged, Szilléri sugár út 26.',
        ]);

        $result = app(LocationMatcher::class)->match(
            $merchant->id,
            '6723 Szeged Szilleri sgt. 26',
            accept: 0.85,
            margin: 0.10,
        );

        $this->assertSame($location->hash_id, $result->accepted()['hash_id'] ?? null);
        $this->assertFalse($result->isAmbiguous());
    }

    #[Test]
    public function a_score_below_the_accept_threshold_is_not_accepted(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'SPAR']);
        MerchantLocation::factory()->for($merchant)->create([
            'address' => '6723 Szeged, Szilléri sugár út 26.',
        ]);

        $result = app(LocationMatcher::class)->match(
            $merchant->id,
            '1052 Budapest, Deák Ferenc tér 3.',
            accept: 0.85,
            margin: 0.10,
        );

        $this->assertNull($result->accepted());
    }

    #[Test]
    public function two_close_branches_are_ambiguous_and_nothing_is_accepted(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'SPAR']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '6723 Szeged, Szilleri sugar ut 26.']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '6723 Szeged, Szilleri sugar ut 28.']);

        $result = app(LocationMatcher::class)->match(
            $merchant->id,
            '6723 Szeged, Szilleri sugar ut 26.',
            accept: 0.85,
            margin: 0.90,
        );

        $this->assertTrue($result->isAmbiguous());
        $this->assertNull($result->accepted());
    }

    #[Test]
    public function without_a_usable_address_every_location_is_listed_unscored(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'SPAR']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '6723 Szeged, Szilleri sugar ut 26.']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '1052 Budapest, Deak Ferenc ter 3.']);

        $result = app(LocationMatcher::class)->match($merchant->id, '---', accept: 0.85, margin: 0.10);

        $this->assertCount(2, $result->all());
        $this->assertSame([null, null], array_column($result->all(), 'score'));
        $this->assertSame([], $result->candidates());
        $this->assertNull($result->accepted());
    }

    #[Test]
    public function another_merchants_branch_is_never_returned(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'SPAR']);
        $other = Merchant::factory()->create(['name' => 'ALDI']);
        MerchantLocation::factory()->for($other)->create(['address' => '6723 Szeged, Szilleri sugar ut 26.']);

        $result = app(LocationMatcher::class)->match(
            $merchant->id,
            '6723 Szeged, Szilleri sugar ut 26.',
            accept: 0.85,
            margin: 0.10,
        );

        $this->assertSame([], $result->all());
    }
}
