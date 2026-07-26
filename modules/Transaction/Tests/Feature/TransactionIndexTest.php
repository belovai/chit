<?php

declare(strict_types=1);

namespace Modules\Transaction\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Transaction\Models\Transaction;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TransactionIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_the_authenticated_users_transactions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $merchant = Merchant::factory()->for($user, 'owner')->create();
        $otherMerchant = Merchant::factory()->for($otherUser, 'owner')->create();
        Transaction::factory()->for($user, 'owner')->for($merchant)->create();
        Transaction::factory()->for($otherUser, 'owner')->for($otherMerchant)->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/transactions');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
