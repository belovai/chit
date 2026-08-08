<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Merchant\Services\AddressNormalizer;
use Modules\Merchant\Services\LocationMatcher;
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

    public function __construct(private readonly LocationMatcher $matcher) {}

    public function handle(StepContext $context): StepResult
    {
        $merchantId = $context->artifact('merchant_candidates')->json()['accepted_id'] ?? null;
        $rawAddress = ArtifactCodec::readReceipt($context)->merchantAddress;

        if (!is_numeric($merchantId) || AddressNormalizer::normalize($rawAddress) === null) {
            return StepResult::success()->artifact('location_candidate', [
                'raw_address' => $rawAddress,
                'accepted_id' => null,
                'accepted_hash_id' => null,
                'candidates' => [],
            ]);
        }

        $match = $this->matcher->match(
            (int) $merchantId,
            $rawAddress,
            (float) config('receipt.matching.location_accept_score'),
            (float) config('receipt.matching.location_ambiguity_margin'),
        );

        // The artifact's candidate entries have always used `name` for the
        // address. Receipts already in the database carry that shape, so the
        // matcher's `address` is mapped rather than renamed here.
        $encoded = array_map(static fn (array $row): array => [
            'id' => $row['id'],
            'hash_id' => $row['hash_id'],
            'name' => $row['address'],
            'score' => $row['score'],
        ], $match->candidates());

        $accepted = $match->accepted();

        $result = StepResult::success()->artifact('location_candidate', [
            'raw_address' => $rawAddress,
            'accepted_id' => $accepted['id'] ?? null,
            'accepted_hash_id' => $accepted['hash_id'] ?? null,
            'candidates' => $encoded,
        ]);

        if ($match->isAmbiguous()) {
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
}
