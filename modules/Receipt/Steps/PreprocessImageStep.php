<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Illuminate\Support\Facades\Storage;
use Modules\Extraction\Exceptions\OcrException;
use Modules\Extraction\Ocr\ImagePreprocessor;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

final class PreprocessImageStep implements PipelineStep
{
    public function __construct(private readonly ImagePreprocessor $preprocessor) {}

    public static function key(): string
    {
        return 'preprocess_image';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.cpu');
    }

    public function handle(StepContext $context): StepResult
    {
        $file = ArtifactCodec::readRawFile($context);

        try {
            $path = $this->preprocessor->normalize($file['disk'], $file['path'], $file['mime']);
        } catch (OcrException $exception) {
            // A corrupt or unsupported file will not fix itself on a retry.
            return StepResult::failure(StepException::permanent($exception->getMessage(), $exception));
        }

        return StepResult::success()->binaryArtifact(
            key: 'normalized_image',
            disk: $file['disk'],
            path: $path,
            mime: 'image/png',
            sizeBytes: (int) Storage::disk($file['disk'])->size($path),
        );
    }
}
