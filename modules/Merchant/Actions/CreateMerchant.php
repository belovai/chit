<?php

declare(strict_types=1);

namespace Modules\Merchant\Actions;

use Modules\Merchant\Models\Merchant;

final class CreateMerchant
{
    /**
     * @param  array{name: string}  $validated
     */
    public function handle(int $ownerId, array $validated): Merchant
    {
        return Merchant::query()->create([
            'owner_id' => $ownerId,
            'name' => $validated['name'],
        ]);
    }
}
