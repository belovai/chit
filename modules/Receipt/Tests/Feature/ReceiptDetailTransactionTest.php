<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\Transaction\Models\Transaction;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The extraction artifacts on the receipt detail hold what the model read, and
 * nothing else — so a merchant the reviewer corrected is invisible there. The
 * recorded transaction is what closes that gap.
 */
final class ReceiptDetailTransactionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_approved_receipt_carries_the_merchant_and_branch_that_were_recorded(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create(['name' => 'OMV']);
        $location = MerchantLocation::factory()->for($merchant)->create([
            'address' => '8175 Balatonfűzfő, Árpád út 1',
        ]);
        $transaction = Transaction::factory()->for($user, 'owner')->create([
            'merchant_id' => $merchant->id,
            'location_id' => $location->id,
        ]);
        $receipt = Receipt::factory()->for($user, 'owner')->create([
            'status' => ReceiptStatus::Approved,
            'transaction_id' => $transaction->id,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/receipts/{$receipt->hash_id}");

        $response->assertOk();
        $response->assertJsonPath('data.transaction.merchant.name', 'OMV');
        $response->assertJsonPath('data.transaction.location.address', '8175 Balatonfűzfő, Árpád út 1');
    }

    #[Test]
    public function a_receipt_without_a_transaction_carries_none(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user, 'owner')->create([
            'status' => ReceiptStatus::NeedsReview,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/receipts/{$receipt->hash_id}");

        $response->assertOk();
        $response->assertJsonPath('data.transaction', null);
    }
}
