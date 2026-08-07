<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\MerchantLocation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantLocationNormalizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_location_fills_the_normalized_address(): void
    {
        $location = MerchantLocation::factory()->create([
            'address' => '6723 Szeged, Szilléri sugár út 26.',
        ]);

        $this->assertSame('6723 szeged szilleri sugar ut 26', $location->normalized_address);
    }

    #[Test]
    public function updating_the_address_recomputes_the_normalized_address(): void
    {
        $location = MerchantLocation::factory()->create(['address' => 'Budapest, Váci u. 1.']);

        $location->update(['address' => 'Erzsébet krt. 5']);

        $this->assertSame('erzsebet korut 5', $location->refresh()->normalized_address);
    }

    #[Test]
    public function a_null_address_leaves_the_normalized_address_null(): void
    {
        $location = MerchantLocation::factory()->online()->create();

        $this->assertNull($location->address);
        $this->assertNull($location->normalized_address);
    }

    #[Test]
    public function the_normalized_column_is_trigram_queryable(): void
    {
        MerchantLocation::factory()->create(['address' => '6723 Szeged, Szilléri sugár út 26.']);

        $found = MerchantLocation::query()
            ->whereRaw('similarity(normalized_address, ?) > ?', ['6723 szeged szilleri sugar ut 26', 0.8])
            ->count();

        $this->assertSame(1, $found);
    }
}
