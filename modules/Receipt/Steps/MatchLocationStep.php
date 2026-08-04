<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Merchant\Models\MerchantLocation;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * Only meaningful once a merchant is accepted, and a miss is unremarkable —
 * most receipts do not name a branch. It therefore emits no finding.
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
        $rawName = ArtifactCodec::readReceipt($context)->merchantName;

        if ($merchantId === null || $rawName === null) {
            return StepResult::success()->artifact('location_candidate', ['accepted_id' => null, 'candidates' => []]);
        }

        $candidates = MerchantLocation::query()
            ->where('merchant_id', $merchantId)
            ->selectRaw('merchant_locations.*, similarity(address, ?) as score', [$rawName])
            ->whereRaw('similarity(address, ?) > ?', [$rawName, (float) config('merchant.matching.threshold')])
            ->orderByDesc('score')
            ->limit((int) config('merchant.matching.limit'))
            ->get();

        $encoded = $candidates->map(static fn (MerchantLocation $location): array => [
            'id' => $location->id,
            'name' => $location->address,
            'score' => round((float) $location->getAttribute('score'), 4),
        ])->values()->all();

        return StepResult::success()->artifact('location_candidate', [
            'accepted_id' => ($encoded[0]['score'] ?? 0) >= (float) config('receipt.matching.merchant_accept_score')
                ? $encoded[0]['id']
                : null,
            'candidates' => $encoded,
        ]);
    }
}
