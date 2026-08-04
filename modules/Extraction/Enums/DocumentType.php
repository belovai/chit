<?php

declare(strict_types=1);

namespace Modules\Extraction\Enums;

enum DocumentType: string
{
    case Receipt = 'receipt';
    case UtilityBill = 'utility_bill';
    case Unknown = 'unknown';

    /** Model output is untrusted — an unrecognised value must not throw. */
    public static function parse(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }
}
