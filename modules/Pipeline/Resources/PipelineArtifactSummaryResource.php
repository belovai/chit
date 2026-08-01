<?php

declare(strict_types=1);

namespace Modules\Pipeline\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pipeline\Models\PipelineArtifact;

/**
 * Metadata only. Payloads (OCR text, raw AI responses) are large and are
 * fetched one at a time from the dedicated artifact endpoint.
 *
 * @mixin PipelineArtifact
 */
final class PipelineArtifactSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'kind' => $this->kind->value,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'is_pruned' => $this->kind->value === 'binary' && $this->path === null,
        ];
    }
}
