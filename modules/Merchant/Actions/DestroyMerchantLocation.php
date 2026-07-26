<?php

declare(strict_types=1);

namespace Modules\Merchant\Actions;

use Modules\Merchant\Models\MerchantLocation;

final class DestroyMerchantLocation
{
    public function handle(MerchantLocation $merchantLocation): void
    {
        $merchantLocation->delete();
    }
}
