<?php

declare(strict_types=1);

namespace Modules\Merchant\Services;

use Modules\Merchant\DataTransferObjects\LocationMatchResult;
use Modules\Merchant\Models\MerchantLocation;

/**
 * A branch is matched on its address, never on the merchant's name, and always
 * within one merchant — the same street address may legitimately belong to two
 * shops in the same mall, and each keeps its own row.
 *
 * Thresholds are arguments rather than config reads: the pipeline's accept
 * score is a Receipt-module concern, and this service belongs to Merchant.
 */
final class LocationMatcher
{
    public function match(int $merchantId, ?string $rawAddress, float $accept, float $margin): LocationMatchResult
    {
        $normalized = AddressNormalizer::normalize($rawAddress);

        $all = $normalized === null
            ? $this->unscored($merchantId)
            : $this->scored($merchantId, $normalized);

        $threshold = (float) config('merchant.matching.threshold');
        $limit = (int) config('merchant.matching.limit');

        $candidates = array_slice(
            array_filter($all, static fn (array $row): bool => $row['score'] !== null && $row['score'] > $threshold),
            0,
            $limit,
        );

        $best = $candidates[0] ?? null;
        $runnerUp = $candidates[1] ?? null;

        $ambiguous = $best !== null && $runnerUp !== null
            && ($best['score'] - $runnerUp['score']) < $margin;

        $accepted = $best !== null && $best['score'] >= $accept && !$ambiguous ? $best : null;

        /** @var list<array{id: int, hash_id: string, address: string, score: float}> $candidates */
        /** @var array{id: int, hash_id: string, address: string, score: float}|null $accepted */
        return new LocationMatchResult($all, $candidates, $accepted, $ambiguous);
    }

    /**
     * @return list<array{id: int, hash_id: string, address: string, score: float|null}>
     */
    private function scored(int $merchantId, string $normalized): array
    {
        return array_values(MerchantLocation::query()
            ->where('merchant_id', $merchantId)
            ->selectRaw('merchant_locations.*, similarity(coalesce(normalized_address, \'\'), ?) as score', [$normalized])
            ->orderByDesc('score')
            ->orderBy('address')
            ->get()
            ->map(static fn (MerchantLocation $location): array => [
                'id' => $location->id,
                'hash_id' => $location->hash_id,
                'address' => (string) $location->address,
                'score' => $location->normalized_address === null
                    ? null
                    : round((float) $location->getAttribute('score'), 4),
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, hash_id: string, address: string, score: float|null}>
     */
    private function unscored(int $merchantId): array
    {
        return array_values(MerchantLocation::query()
            ->where('merchant_id', $merchantId)
            ->orderBy('address')
            ->get()
            ->map(static fn (MerchantLocation $location): array => [
                'id' => $location->id,
                'hash_id' => $location->hash_id,
                'address' => (string) $location->address,
                'score' => null,
            ])
            ->all());
    }
}
