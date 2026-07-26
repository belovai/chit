<?php

declare(strict_types=1);

namespace Modules\Transaction\Actions;

use Modules\Transaction\Models\Transaction;

final class DestroyTransaction
{
    public function handle(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
