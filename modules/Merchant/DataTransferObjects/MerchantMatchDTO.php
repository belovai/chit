<?php

declare(strict_types=1);

namespace Modules\Merchant\DataTransferObjects;

use Modules\Merchant\Models\Merchant;

final readonly class MerchantMatchDTO
{
    public function __construct(
        public Merchant $merchant,
        public float $score,
    ) {}
}
