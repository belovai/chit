<?php

declare(strict_types=1);

namespace Modules\Transaction\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Product\Models\Product;
use Modules\Transaction\Enums\PaymentMethod;
use Modules\Transaction\Enums\TransactionSource;
use Modules\Transaction\Models\Transaction;
use Modules\Transaction\Models\TransactionItem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TransactionModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_a_hash_id_on_creation(): void
    {
        $transaction = Transaction::factory()->create();

        $this->assertNotEmpty($transaction->hash_id);
    }

    #[Test]
    public function it_soft_deletes(): void
    {
        $transaction = Transaction::factory()->create();

        $transaction->delete();

        $this->assertSoftDeleted($transaction);
    }

    #[Test]
    public function it_casts_source_and_payment_method_to_enums(): void
    {
        $transaction = Transaction::factory()->create([
            'source' => TransactionSource::Receipt,
            'payment_method' => PaymentMethod::Cash,
        ]);

        $this->assertSame(TransactionSource::Receipt, $transaction->fresh()->source);
        $this->assertSame(PaymentMethod::Cash, $transaction->fresh()->payment_method);
    }

    #[Test]
    public function it_cascade_deletes_items_when_hard_deleted(): void
    {
        $transaction = Transaction::factory()->create();
        TransactionItem::factory()->for($transaction)->create();

        $transaction->forceDelete();

        $this->assertDatabaseCount('transaction_items', 0);
    }

    #[Test]
    public function it_still_resolves_a_soft_deleted_merchant_through_the_relation(): void
    {
        $merchant = Merchant::factory()->create();
        $transaction = Transaction::factory()->for($merchant)->create();
        $merchant->delete();

        $this->assertSame($merchant->id, $transaction->fresh()->merchant->id);
    }

    #[Test]
    public function it_still_resolves_a_soft_deleted_product_through_the_item_relation(): void
    {
        $product = Product::factory()->create();
        $transaction = Transaction::factory()->create();
        $item = TransactionItem::factory()->for($transaction)->create(['product_id' => $product->id]);
        $product->delete();

        $this->assertSame($product->id, $item->fresh()->product->id);
    }
}
