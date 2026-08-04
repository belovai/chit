<?php

declare(strict_types=1);

namespace Modules\Receipt\Listeners;

use Modules\Pipeline\Events\ArtifactPublished;
use Modules\Receipt\Models\Receipt;

/**
 * The single writer of the receipt columns that are pure caches of an
 * artifact's payload (doc_type, series_key). A step publishes the artifact;
 * this listener is the only thing that copies its value onto the entity.
 */
final class ProjectReceiptFields
{
    /** Artifact key => receipts column it projects onto. */
    private const PROJECTED = [
        'doc_type' => 'doc_type',
        'series_key' => 'series_key',
    ];

    public function handle(ArtifactPublished $event): void
    {
        $column = self::PROJECTED[$event->artifact->key] ?? null;

        if ($column === null) {
            return;
        }

        $run = $event->artifact->run;

        if ($run === null || $run->subject_type !== Receipt::class) {
            return;
        }

        $receipt = Receipt::query()->find($run->subject_id);

        if ($receipt === null || $receipt->current_run_id !== $run->id) {
            // A stale run (e.g. a superseded retry) must not overwrite what
            // the receipt's current run has already projected.
            return;
        }

        $receipt->update([$column => $event->artifact->payload['value'] ?? null]);
    }
}
