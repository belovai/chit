<?php

declare(strict_types=1);

namespace Modules\Merchant\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Requests\SuggestMerchantLocationsRequest;
use Modules\Merchant\Services\LocationMatcher;

/**
 * The review screen has to say whether approving will select an existing branch
 * or create one, and it has to keep saying the truth after the reviewer swaps
 * the merchant. That answer is the matcher's, computed with the same thresholds
 * the pipeline uses, so the screen and the write cannot drift apart.
 */
final class SuggestMerchantLocationsController
{
    use ApiResponses;

    public function __invoke(
        Merchant $merchant,
        SuggestMerchantLocationsRequest $request,
        LocationMatcher $matcher,
    ): JsonResponse {
        $address = $request->validated('address');

        $match = $matcher->match(
            $merchant->id,
            is_string($address) ? $address : null,
            (float) config('receipt.matching.location_accept_score'),
            (float) config('receipt.matching.location_ambiguity_margin'),
        );

        return $this->ok(data: [
            'candidates' => array_map(static fn (array $row): array => [
                'hash_id' => $row['hash_id'],
                'address' => $row['address'],
                'score' => $row['score'],
            ], $match->all()),
            'accepted_hash_id' => $match->accepted()['hash_id'] ?? null,
            'ambiguous' => $match->isAmbiguous(),
        ]);
    }
}
