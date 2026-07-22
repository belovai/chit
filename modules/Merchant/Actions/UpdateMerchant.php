<?php

declare(strict_types=1);

namespace Modules\Merchant\Actions;

use Modules\Merchant\Models\Merchant;

final class UpdateMerchant
{
    /**
     * @param  array{name: string}  $validated
     */
    public function handle(Merchant $merchant, array $validated): Merchant
    {
        $merchant->update([
            'name' => $validated['name'],
        ]);

        return $merchant->refresh();
    }
}
