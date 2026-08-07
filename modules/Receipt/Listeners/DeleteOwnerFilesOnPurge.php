<?php

declare(strict_types=1);

namespace Modules\Receipt\Listeners;

use Illuminate\Support\Facades\Storage;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Receipt\Models\Receipt;
use Modules\User\Events\UserPurging;

/**
 * On account deletion, the `users` cascade handles the DB rows, but nothing
 * handles the files written to disk. Artifact files also disappear here, not
 * in the Pipeline module: the Pipeline deliberately knows no domain concepts
 * (so it doesn't know about the user either).
 */
final class DeleteOwnerFilesOnPurge
{
    public function handle(UserPurging $event): void
    {
        $this->deleteReceiptFiles($event->userId);
        $this->deleteArtifactFiles($event->userId);
    }

    private function deleteReceiptFiles(int $userId): void
    {
        Receipt::withTrashed()
            ->where('owner_id', $userId)
            ->chunkById(100, function ($receipts): void {
                foreach ($receipts as $receipt) {
                    Storage::disk($receipt->disk)->delete($receipt->path);
                }
            });
    }

    private function deleteArtifactFiles(int $userId): void
    {
        $runIds = PipelineRun::query()
            ->where('owner_id', $userId)
            ->select('id');

        PipelineArtifact::query()
            ->whereIn('run_id', $runIds)
            ->whereNotNull('path')
            ->chunkById(100, function ($artifacts): void {
                foreach ($artifacts as $artifact) {
                    Storage::disk((string) $artifact->disk)->delete((string) $artifact->path);
                }
            });
    }
}
