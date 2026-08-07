<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Merchant\Models\MerchantLocation;
use Modules\Merchant\Services\AddressNormalizer;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * A branch is matched on its address, not on the merchant's name — the old
 * implementation compared the two, which could never hit. A miss is worth
 * asking about (it creates a location row), but a receipt that names no branch
 * at all is unremarkable and stays silent.
 */
final class MatchLocationStep implements PipelineStep
{
    public static function key(): string
    {
        return 'match_location';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $merchantId = $context->artifact('merchant_candidates')->json()['accepted_id'] ?? null;
        $rawAddress = ArtifactCodec::readReceipt($context)->merchantAddress;
        $normalized = AddressNormalizer::normalize($rawAddress);

        if (!is_numeric($merchantId) || $normalized === null) {
            return StepResult::success()->artifact('location_candidate', [
                'raw_address' => $rawAddress,
                'accepted_id' => null,
                'accepted_hash_id' => null,
                'candidates' => [],
            ]);
        }

        $encoded = $this->candidates((int) $merchantId, $normalized);

        $accept = (float) config('receipt.matching.location_accept_score');
        $margin = (float) config('receipt.matching.location_ambiguity_margin');

        $best = $encoded[0] ?? null;
        $runnerUp = $encoded[1] ?? null;

        $isAmbiguous = $best !== null && $runnerUp !== null
            && ($best['score'] - $runnerUp['score']) < $margin;

        $accepted = $best !== null && $best['score'] >= $accept && !$isAmbiguous ? $best : null;

        $result = StepResult::success()->artifact('location_candidate', [
            'raw_address' => $rawAddress,
            'accepted_id' => $accepted['id'] ?? null,
            'accepted_hash_id' => $accepted['hash_id'] ?? null,
            'candidates' => $encoded,
        ]);

        if ($isAmbiguous) {
            return $result->finding(Finding::warning('location_ambiguous', context: [
                'raw_address' => $rawAddress,
                'candidates' => array_slice($encoded, 0, 3),
            ]));
        }

        if ($accepted === null) {
            // Same shape as new_merchant: not an error, just how a branch first
            // enters the system. The next receipt from it passes silently.
            return $result->finding(Finding::warning('new_location', context: [
                'raw_address' => $rawAddress,
            ]));
        }

        return $result;
    }

    /**
     * @return list<array{id: int, hash_id: string, name: string, score: float}>
     */
    private function candidates(int $merchantId, string $normalized): array
    {
        return array_values(MerchantLocation::query()
            ->where('merchant_id', $merchantId)
            ->whereNotNull('normalized_address')
            ->selectRaw('merchant_locations.*, similarity(normalized_address, ?) as score', [$normalized])
            ->whereRaw('similarity(normalized_address, ?) > ?', [$normalized, (float) config('merchant.matching.threshold')])
            ->orderByDesc('score')
            ->limit((int) config('merchant.matching.limit'))
            ->get()
            ->map(static fn (MerchantLocation $location): array => [
                'id' => $location->id,
                'hash_id' => $location->hash_id,
                'name' => (string) $location->address,
                'score' => round((float) $location->getAttribute('score'), 4),
            ])
            ->all());
    }
}
