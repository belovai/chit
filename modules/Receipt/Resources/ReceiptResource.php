<?php

declare(strict_types=1);

namespace Modules\Receipt\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Receipt\Models\Receipt;

/**
 * @mixin Receipt
 */
final class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'hash_id' => $this->hash_id,
            'original_filename' => $this->original_filename,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'status' => $this->status->value,
            'doc_type' => $this->doc_type?->value,
            'run_hash_id' => $this->currentRun?->hash_id,
            'transaction_hash_id' => $this->transaction?->hash_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
