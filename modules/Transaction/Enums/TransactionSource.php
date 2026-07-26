<?php

declare(strict_types=1);

namespace Modules\Transaction\Enums;

use App\Traits\EnumCompares;

enum TransactionSource: string
{
    use EnumCompares;

    case Manual = 'manual';
    case Receipt = 'receipt';
}
