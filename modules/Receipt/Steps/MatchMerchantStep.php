<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Merchant\Actions\SuggestMerchantCandidates;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * Normalising merchant names is a first-class concern: "OMV Hodmezovasarhely 2"
 * and "OMV Hmvhely" must resolve to one merchant or every later query fragments.
 * This step proposes, it never creates — a new merchant row is only written on
 * approval, in create_transaction.
 */
final class MatchMerchantStep implements PipelineStep
{
    public function __construct(private readonly SuggestMerchantCandidates $suggest) {}

    public static function key(): string
    {
        return 'match_merchant';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $name = ArtifactCodec::readReceipt($context)->merchantName;

        return MerchantMatching::resolve(
            suggest: $this->suggest,
            ownerId: $context->ownerId(),
            rawName: $name,
        );
    }
}
