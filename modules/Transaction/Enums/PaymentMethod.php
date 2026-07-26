<?php

declare(strict_types=1);

namespace Modules\Transaction\Enums;

use App\Traits\EnumCompares;

enum PaymentMethod: string
{
    use EnumCompares;

    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
}
