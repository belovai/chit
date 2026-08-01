<?php

declare(strict_types=1);

namespace Modules\Pipeline\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Models\PipelineArtifact;

/**
 * Deletes the bytes of expired binary artifacts, keeping the row so a run detail
 * page can still show that the artifact existed and was pruned.
 */
final class PruneArtifacts extends Command
{
    protected $signature = 'pipeline:prune-artifacts';

    protected $description = 'Delete the files of expired binary pipeline artifacts';

    public function handle(): int
    {
        $pruned = 0;

        PipelineArtifact::query()
            ->where('kind', ArtifactKind::Binary->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNotNull('path')
            ->chunkById(100, function ($artifacts) use (&$pruned): void {
                foreach ($artifacts as $artifact) {
                    Storage::disk((string) $artifact->disk)->delete((string) $artifact->path);
                    $artifact->update(['path' => null, 'size_bytes' => null]);
                    $pruned++;
                }
            });

        $this->info("Pruned {$pruned} artifact file(s).");

        return self::SUCCESS;
    }
}
