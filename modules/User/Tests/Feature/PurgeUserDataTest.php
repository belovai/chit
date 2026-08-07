<?php

declare(strict_types=1);

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Product\Models\Product;
use Modules\Receipt\Models\Receipt;
use Modules\Transaction\Models\Transaction;
use Modules\Transaction\Models\TransactionItem;
use Modules\User\Jobs\PurgeUserData;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PurgeUserDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function purge_force_deletes_the_user_and_cascades_owned_rows(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $user->delete();
        $receipt = Receipt::factory()->create(['owner_id' => $user->id]);

        (new PurgeUserData($user->id))->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('receipts', ['id' => $receipt->id]);
    }

    /**
     * A táblák RESTRICT FK-kal is hivatkoznak egymásra (transaction_items ->
     * products, transactions -> merchants/merchant_locations), amibe egy sima
     * `users` cascade beleütközne.
     */
    #[Test]
    public function purge_deletes_owned_rows_across_restricting_foreign_keys(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $merchant = Merchant::factory()->create(['owner_id' => $user->id]);
        $location = MerchantLocation::factory()->create(['merchant_id' => $merchant->id]);
        $product = Product::factory()->create(['owner_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'owner_id' => $user->id,
            'merchant_id' => $merchant->id,
            'location_id' => $location->id,
        ]);
        $item = TransactionItem::factory()->create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
        ]);
        $user->delete();

        (new PurgeUserData($user->id))->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('transaction_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('merchant_locations', ['id' => $location->id]);
        $this->assertDatabaseMissing('merchants', ['id' => $merchant->id]);
    }

    #[Test]
    public function purge_deletes_receipt_files_from_disk(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $receipt = Receipt::factory()->create(['owner_id' => $user->id, 'disk' => 'local']);
        Storage::disk('local')->put($receipt->path, 'bytes');
        $user->delete();

        (new PurgeUserData($user->id))->handle();

        Storage::disk('local')->assertMissing($receipt->path);
    }

    #[Test]
    public function purge_deletes_binary_artifact_files_from_disk(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $artifact = PipelineArtifact::factory()->create([
            'disk' => 'local',
            'path' => 'artifacts/normalized.jpg',
        ]);
        $artifact->run->update(['owner_id' => $user->id]);
        Storage::disk('local')->put((string) $artifact->path, 'bytes');
        $user->delete();

        (new PurgeUserData($user->id))->handle();

        Storage::disk('local')->assertMissing((string) $artifact->path);
        $this->assertDatabaseMissing('pipeline_artifacts', ['id' => $artifact->id]);
    }

    #[Test]
    public function purge_leaves_other_users_files_alone(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherReceipt = Receipt::factory()->create(['owner_id' => $other->id, 'disk' => 'local']);
        Storage::disk('local')->put($otherReceipt->path, 'bytes');
        $user->delete();

        (new PurgeUserData($user->id))->handle();

        Storage::disk('local')->assertExists($otherReceipt->path);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    #[Test]
    public function purge_is_a_no_op_for_an_unknown_user(): void
    {
        (new PurgeUserData(999_999))->handle();

        $this->assertDatabaseCount('users', 0);
    }
}
