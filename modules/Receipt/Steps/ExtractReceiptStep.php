<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Exceptions\NoActiveAiCredentialException;
use Modules\Ai\Services\AiConnectionResolver;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Extraction\Ai\Contracts\DocumentExtractor;
use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

final class ExtractReceiptStep implements PipelineStep
{
    public function __construct(
        private readonly DocumentExtractor $extractor,
        private readonly AiConnectionResolver $connections,
    ) {}

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
            $connection = $this->resolveConnection($context);

            $extraction = $this->extractor->extract(
                on: $connection,
                imageBytes: $image->contents(),
                mimeType: 'image/png',
                type: DocumentType::Receipt,
            );
        } catch (NoActiveAiCredentialException $exception) {
            // A missing key is the user's to fix; retrying cannot help.
            return StepResult::failure(StepException::permanent($exception->getMessage(), $exception));
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

    private function resolveConnection(StepContext $context): AiConnection
    {
        $credentialId = $context->aiCredentialId();

        if ($credentialId === null) {
            throw NoActiveAiCredentialException::forUser($context->ownerId());
        }

        return $this->connections->forCredentialId($credentialId);
    }
}
