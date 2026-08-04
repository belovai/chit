<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Extraction\Ai\Contracts\DocumentExtractor;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Exceptions\AiException;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

final class ExtractReceiptStep implements PipelineStep
{
    public function __construct(private readonly DocumentExtractor $extractor) {}

    public static function key(): string
    {
        return 'extract_receipt';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.ai');
    }

    public function handle(StepContext $context): StepResult
    {
        $image = $context->artifact('normalized_image');

        try {
            $extraction = $this->extractor->extract(
                imageBytes: $image->contents(),
                mimeType: 'image/png',
                type: DocumentType::Receipt,
            );
        } catch (AiException $exception) {
            return StepResult::failure(
                $exception->isRetryable()
                    ? StepException::retryable($exception->getMessage(), $exception)
                    : StepException::permanent($exception->getMessage(), $exception),
            );
        }

        return StepResult::success()
            // The verbatim model payload is the artifact — it is both the input
            // for downstream steps and the audit record the brief requires.
            ->artifact('extracted_receipt', [
                'payload' => $extraction->rawResponse,
                'confidence' => $extraction->confidence,
            ])
            ->confidence($extraction->confidence)
            ->cost(
                inputTokens: $extraction->usage->inputTokens,
                outputTokens: $extraction->usage->outputTokens,
                usdMicros: $extraction->usage->costUsdMicros,
            );
    }
}
