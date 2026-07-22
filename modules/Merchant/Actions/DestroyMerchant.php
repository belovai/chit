<?php

declare(strict_types=1);

namespace Modules\Merchant\Actions;

use Modules\Merchant\Models\Merchant;

final class DestroyMerchant
{
    public function handle(Merchant $merchant): void
    {
        $merchant->delete();
    }
}
