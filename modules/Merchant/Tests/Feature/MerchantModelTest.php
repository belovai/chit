<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Merchant\Models\Merchant;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MerchantModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_a_hash_id_on_creation(): void
    {
        $merchant = Merchant::factory()->create();

        $this->assertNotEmpty($merchant->hash_id);
    }

    #[Test]
    public function it_soft_deletes(): void
    {
        $merchant = Merchant::factory()->create();

        $merchant->delete();

        $this->assertSoftDeleted($merchant);
    }

    #[Test]
    public function it_prevents_duplicate_names_case_insensitively_for_the_same_owner(): void
    {
        $owner = User::factory()->create();
        Merchant::factory()->for($owner, 'owner')->create(['name' => 'OMV']);

        $this->expectException(QueryException::class);

        Merchant::factory()->for($owner, 'owner')->create(['name' => 'omv']);
    }

    #[Test]
    public function it_allows_the_same_name_for_different_owners(): void
    {
        Merchant::factory()->create(['name' => 'OMV']);
        $merchant = Merchant::factory()->create(['name' => 'OMV']);

        $this->assertSame('OMV', $merchant->name);
    }

    #[Test]
    public function pg_trgm_similarity_is_queryable(): void
    {
        Merchant::factory()->create(['name' => 'OMV Hodmezovasarhely 2']);

        $row = DB::selectOne(
            'select similarity(name, ?) as score from merchants limit 1',
            ['OMV Hodmezovasarhely'],
        );

        $this->assertGreaterThan(0, $row->score);
    }
}
