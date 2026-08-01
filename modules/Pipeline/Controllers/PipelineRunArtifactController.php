<?php

declare(strict_types=1);

namespace Modules\Pipeline\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Models\PipelineRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PipelineRunArtifactController
{
    use ApiResponses;

    public function __invoke(PipelineRun $pipelineRun, string $key): JsonResponse|StreamedResponse
    {
        $artifact = $pipelineRun->artifacts()
            ->where('key', $key)
            ->whereNull('superseded_at')
            ->first();

        abort_if($artifact === null, 404);

        if ($artifact->kind !== ArtifactKind::Binary) {
            return $this->ok(data: [
                'key' => $artifact->key,
                'kind' => $artifact->kind->value,
                'payload' => $artifact->payload,
            ]);
        }

        abort_if($artifact->path === null, 404);

        return Storage::disk((string) $artifact->disk)->download(
            $artifact->path,
            $artifact->key,
            $artifact->mime === null ? [] : ['Content-Type' => $artifact->mime],
        );
    }
}
