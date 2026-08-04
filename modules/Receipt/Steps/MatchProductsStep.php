<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Product\Actions\SuggestProductCandidates;
use Modules\Product\DataTransferObjects\ProductMatchDTO;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * Suggests a known product per line item. Unmatched items are the normal case
 * on a first visit, so this step never blocks — the review screen offers the
 * candidates and the user decides.
 */
final class MatchProductsStep implements PipelineStep
{
    public function __construct(private readonly SuggestProductCandidates $suggest) {}

    public static function key(): string
    {
        return 'match_products';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $accept = (float) config('receipt.matching.product_accept_score');
        $items = [];

        foreach (ArtifactCodec::readReceipt($context)->items as $index => $item) {
            $candidates = $this->suggest->handle($context->ownerId(), $item->description)
                ->map(static fn (ProductMatchDTO $match): array => [
                    'id' => $match->product->id,
                    'hash_id' => $match->product->hash_id,
                    'name' => $match->product->name,
                    'score' => round($match->score, 4),
                ])->values()->all();

            $items[] = [
                'item_index' => $index,
                'description' => $item->description,
                'accepted_id' => ($candidates[0]['score'] ?? 0) >= $accept ? $candidates[0]['id'] : null,
                'candidates' => $candidates,
            ];
        }

        return StepResult::success()->artifact('product_matches', ['items' => $items]);
    }
}
