<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * Catches the cheapest duplicate: the same bytes uploaded twice. Content-level
 * duplicates (a reprint, a photo of the same receipt) are caught later by
 * dedupe_content, which needs the extraction to have run first.
 */
final class DedupeFileHashStep implements PipelineStep
{
    public static function key(): string
    {
        return 'dedupe_file_hash';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $receipt = ArtifactCodec::subject($context);
        $hash = (string) ($context->artifact('file_hash')->json()['value'] ?? '');

        $duplicate = Receipt::query()
            ->where('owner_id', $context->ownerId())
            ->where('file_hash', $hash)
            ->whereKeyNot($receipt->id)
            ->first();

        $result = StepResult::success();

        if ($duplicate !== null) {
            $result->finding(Finding::blocker('exact_duplicate', context: [
                'receipt_hash_id' => $duplicate->hash_id,
                'uploaded_at' => $duplicate->created_at?->toIso8601String(),
            ]));
        }

        return $result;
    }
}
