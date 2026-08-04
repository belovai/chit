<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Illuminate\Support\Facades\Storage;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * Publishes the uploaded file as artifacts. It does not write the file — upload
 * already did — but every later step reads its input from artifacts only, so
 * the file reference has to enter the run through this door.
 */
final class StoreFileStep implements PipelineStep
{
    public static function key(): string
    {
        return 'store_file';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $receipt = ArtifactCodec::subject($context);

        if (!Storage::disk($receipt->disk)->exists($receipt->path)) {
            return StepResult::failure(
                StepException::permanent("Uploaded file [{$receipt->path}] is missing from disk [{$receipt->disk}]."),
            );
        }

        return StepResult::success()
            ->artifact('raw_file', ArtifactCodec::rawFile($receipt))
            ->artifact('file_hash', ['value' => $receipt->file_hash]);
    }
}
