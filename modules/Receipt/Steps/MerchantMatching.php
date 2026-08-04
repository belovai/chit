<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Illuminate\Support\Collection;
use Modules\Merchant\Actions\SuggestMerchantCandidates;
use Modules\Merchant\DataTransferObjects\MerchantMatchDTO;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepResult;

final class MerchantMatching
{
    public static function resolve(SuggestMerchantCandidates $suggest, int $ownerId, ?string $rawName): StepResult
    {
        if ($rawName === null) {
            return StepResult::success()
                ->artifact('merchant_candidates', ['raw_name' => null, 'accepted_id' => null, 'candidates' => []])
                ->finding(Finding::blocker('merchant_missing'));
        }

        /** @var Collection<int, MerchantMatchDTO> $candidates */
        $candidates = $suggest->handle($ownerId, $rawName);

        $encoded = $candidates->map(static fn (MerchantMatchDTO $match): array => [
            'id' => $match->merchant->id,
            'hash_id' => $match->merchant->hash_id,
            'name' => $match->merchant->name,
            'score' => round($match->score, 4),
        ])->values()->all();

        $accept = (float) config('receipt.matching.merchant_accept_score');
        $margin = (float) config('receipt.matching.merchant_ambiguity_margin');

        $best = $encoded[0] ?? null;
        $runnerUp = $encoded[1] ?? null;

        $isAmbiguous = $best !== null && $runnerUp !== null
            && ($best['score'] - $runnerUp['score']) < $margin;

        $acceptedId = $best !== null && $best['score'] >= $accept && !$isAmbiguous
            ? $best['id']
            : null;

        $result = StepResult::success()->artifact('merchant_candidates', [
            'raw_name' => $rawName,
            'accepted_id' => $acceptedId,
            'candidates' => $encoded,
        ]);

        if ($isAmbiguous) {
            return $result->finding(Finding::warning('merchant_ambiguous', context: [
                'raw_name' => $rawName,
                'candidates' => array_slice($encoded, 0, 3),
            ]));
        }

        if ($acceptedId === null) {
            // Not an error — it is how a merchant gets into the system. The
            // second receipt from this shop will match and pass silently.
            return $result->finding(Finding::warning('new_merchant', context: ['raw_name' => $rawName]));
        }

        return $result;
    }
}
