<?php

declare(strict_types=1);

namespace Modules\Merchant\Actions;

use Illuminate\Support\Collection;
use Modules\Merchant\DataTransferObjects\MerchantMatchDTO;
use Modules\Merchant\Models\Merchant;

final class SuggestMerchantCandidates
{
    /**
     * @return Collection<int, MerchantMatchDTO>
     */
    public function handle(int $ownerId, string $rawName, ?float $threshold = null, ?int $limit = null): Collection
    {
        $threshold ??= (float) config('merchant.matching.threshold');
        $limit ??= (int) config('merchant.matching.limit');

        return Merchant::query()
            ->where('owner_id', $ownerId)
            ->selectRaw('merchants.*, similarity(name, ?) as score', [$rawName])
            ->whereRaw('similarity(name, ?) > ?', [$rawName, $threshold])
            ->orderByDesc('score')
            ->limit($limit)
            ->get()
            ->map(fn (Merchant $merchant): MerchantMatchDTO => new MerchantMatchDTO(
                merchant: $merchant,
                score: (float) $merchant->getAttribute('score'),
            ));
    }
}
