<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantLocationModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_a_hash_id_on_creation(): void
    {
        $location = MerchantLocation::factory()->create();

        $this->assertNotEmpty($location->hash_id);
    }

    #[Test]
    public function it_soft_deletes(): void
    {
        $location = MerchantLocation::factory()->create();

        $location->delete();

        $this->assertSoftDeleted($location);
    }

    #[Test]
    public function it_belongs_to_a_merchant_and_the_merchant_lists_it_back(): void
    {
        $merchant = Merchant::factory()->create();
        $location = MerchantLocation::factory()->for($merchant)->create();

        $this->assertTrue($location->merchant->is($merchant));
        $this->assertTrue($merchant->locations->contains($location));
    }

    #[Test]
    public function the_online_factory_state_has_no_address(): void
    {
        $location = MerchantLocation::factory()->online()->create();

        $this->assertTrue($location->is_online);
        $this->assertNull($location->address);
    }
}
