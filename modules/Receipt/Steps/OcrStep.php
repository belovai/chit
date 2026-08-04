<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Extraction\Exceptions\OcrException;
use Modules\Extraction\Ocr\Contracts\OcrEngine;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

final class OcrStep implements PipelineStep
{
    /** Below this mean confidence the text is worth a human glance. */
    private const LOW_CONFIDENCE = 0.60;

    public function __construct(private readonly OcrEngine $engine) {}

    public static function key(): string
    {
        return 'ocr';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.cpu');
    }

    public function handle(StepContext $context): StepResult
    {
        $image = $context->artifact('normalized_image');

        try {
            $ocr = $this->engine->read((string) $image->disk, (string) $image->path);
        } catch (OcrException $exception) {
            // A timeout is worth another attempt; anything else is deterministic.
            return StepResult::failure(
                str_contains(mb_strtolower($exception->getMessage()), 'timed out')
                    ? StepException::retryable($exception->getMessage(), $exception)
                    : StepException::permanent($exception->getMessage(), $exception),
            );
        }

        $result = StepResult::success()
            ->textArtifact('ocr_text', $ocr->text)
            ->artifact('ocr_confidence', [
                'mean' => $ocr->meanConfidence,
                'pages' => $ocr->pageConfidences,
                'engine' => $ocr->engine,
            ])
            ->confidence($ocr->meanConfidence);

        if ($ocr->meanConfidence < self::LOW_CONFIDENCE) {
            $result->finding(Finding::warning('low_ocr_confidence', context: [
                'mean' => $ocr->meanConfidence,
            ]));
        }

        return $result;
    }
}
