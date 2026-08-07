<?php

declare(strict_types=1);

namespace Modules\Receipt\Listeners;

use Illuminate\Support\Facades\Storage;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Receipt\Models\Receipt;
use Modules\User\Events\UserPurging;

/**
 * Fiók törlésekor a DB sorokat a `users` cascade viszi, a diszkre írt fájlokat
 * viszont senki. Az artifact fájlok is itt tűnnek el, nem a Pipeline modulban:
 * a Pipeline szándékosan nem ismer domain fogalmakat (így a felhasználót sem).
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
