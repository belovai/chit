<?php

declare(strict_types=1);

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Actions\CreateProduct;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateProductTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_new_product(): void
    {
        $owner = User::factory()->create();

        $product = app(CreateProduct::class)->handle($owner->id, ['name' => 'Riska tej 2.8%']);

        $this->assertSame('Riska tej 2.8%', $product->name);
        $this->assertDatabaseCount('products', 1);
    }

    #[Test]
    public function it_returns_the_existing_product_instead_of_colliding_case_insensitively(): void
    {
        $owner = User::factory()->create();
        $existing = Product::factory()->for($owner, 'owner')->create(['name' => 'Riska tej 2.8%']);

        $product = app(CreateProduct::class)->handle($owner->id, ['name' => 'RISKA TEJ 2.8%']);

        $this->assertTrue($product->is($existing));
        $this->assertDatabaseCount('products', 1);
    }

    #[Test]
    public function two_receipt_lines_for_the_same_new_product_do_not_collide(): void
    {
        $owner = User::factory()->create();
        $action = app(CreateProduct::class);

        $first = $action->handle($owner->id, ['name' => 'Friss 0,0% Gör-Eper 0,5l']);
        $second = $action->handle($owner->id, ['name' => 'FRISS 0,0% GÖR-EPER 0,5L']);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('products', 1);
    }
}
