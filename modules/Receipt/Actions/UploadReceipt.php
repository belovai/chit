<?php

declare(strict_types=1);

namespace Modules\Receipt\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Ai\Exceptions\NoActiveAiCredentialException;
use Modules\Ai\Services\AiConnectionResolver;
use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;

final class UploadReceipt
{
    public function __construct(
        private readonly StartRun $startRun,
        private readonly AiConnectionResolver $connections,
    ) {}

    public function handle(int $ownerId, UploadedFile $file, ?DocumentType $hint = null): Receipt
    {
        // Resolved first so a user without a key gets a synchronous, actionable
        // error instead of a failed job thirty seconds later — and so no orphan
        // file or receipt row is left behind.
        $credential = $this->connections->activeCredentialFor($ownerId);

        if ($credential === null || !$credential->status->isUsable()) {
            throw NoActiveAiCredentialException::forUser($ownerId);
        }

        $disk = (string) config('receipt.upload.disk');
        $path = 'receipts/'.Str::uuid()->toString().'.'.($file->guessExtension() ?? 'bin');
        $contents = (string) file_get_contents($file->getRealPath());

        // The file lands on disk before the row exists because the row stores
        // its hash — and the hash is what dedupe_file_hash keys on.
        $file->storeAs('', $path, ['disk' => $disk]);

        $receipt = DB::transaction(fn (): Receipt => Receipt::query()->create([
            'owner_id' => $ownerId,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'file_hash' => hash('sha256', $contents),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'doc_type_hint' => $hint,
            'status' => ReceiptStatus::Pending,
        ]));

        $run = $this->startRun->handle(
            definitionKey: 'receipt_ingest',
            ownerId: $ownerId,
            subject: $receipt,
            trigger: TriggerSource::ManualUpload,
            aiCredentialId: $credential->id,
        );

        $receipt->update(['current_run_id' => $run->id]);

        return $receipt->refresh();
    }
}
