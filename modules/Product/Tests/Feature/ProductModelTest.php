<?php

declare(strict_types=1);

namespace Modules\Product\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_a_hash_id_on_creation(): void
    {
        $product = Product::factory()->create();

        $this->assertNotEmpty($product->hash_id);
    }

    #[Test]
    public function it_soft_deletes(): void
    {
        $product = Product::factory()->create();

        $product->delete();

        $this->assertSoftDeleted($product);
    }

    #[Test]
    public function it_prevents_duplicate_names_case_insensitively_for_the_same_owner(): void
    {
        $owner = User::factory()->create();
        Product::factory()->for($owner, 'owner')->create(['name' => 'Riska tej 2.8%']);

        $this->expectException(QueryException::class);

        Product::factory()->for($owner, 'owner')->create(['name' => 'riska tej 2.8%']);
    }

    #[Test]
    public function it_allows_the_same_name_for_different_owners(): void
    {
        Product::factory()->create(['name' => 'Riska tej 2.8%']);
        $product = Product::factory()->create(['name' => 'Riska tej 2.8%']);

        $this->assertSame('Riska tej 2.8%', $product->name);
    }

    #[Test]
    public function pg_trgm_similarity_is_queryable(): void
    {
        Product::factory()->create(['name' => 'Riska tej 2.8% UHT 1L']);

        $row = DB::selectOne(
            'select similarity(name, ?) as score from products limit 1',
            ['Riska tej 2.8%'],
        );

        $this->assertGreaterThan(0, $row->score);
    }
}
