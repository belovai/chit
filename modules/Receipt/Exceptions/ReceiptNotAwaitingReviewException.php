<?php

declare(strict_types=1);

namespace Modules\Receipt\Exceptions;

use Modules\Receipt\Models\Receipt;
use RuntimeException;

final class ReceiptNotAwaitingReviewException extends RuntimeException
{
    public static function for(Receipt $receipt): self
    {
        return new self("Receipt [{$receipt->hash_id}] is not waiting for a review decision.");
    }
}
