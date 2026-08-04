<?php

declare(strict_types=1);

namespace Modules\Receipt\Enums;

enum ReceiptStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Canceled = 'canceled';
}
