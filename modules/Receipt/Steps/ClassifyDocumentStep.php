<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Extraction\Ai\Contracts\DocumentClassifier;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Exceptions\AiException;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepDefinition;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * The branch point. One cheap call decides which of two very different
 * extraction paths the run takes, and expands the run accordingly — the same
 * mechanism a third document type would use later.
 */
final class ClassifyDocumentStep implements PipelineStep
{
    /** Below this we do not trust the branch decision enough to act on it. */
    private const MIN_CONFIDENCE = 0.60;

    public function __construct(private readonly DocumentClassifier $classifier) {}

    public static function key(): string
    {
        return 'classify_document';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.ai');
    }

    public function handle(StepContext $context): StepResult
    {
        $receipt = ArtifactCodec::subject($context);
        $reviewed = $this->reviewerDecision($context);

        if ($reviewed !== null) {
            return StepResult::success()
                ->artifact('doc_type', [
                    'value' => $reviewed->value,
                    'confidence' => 1.0,
                    'reason' => 'Chosen by the reviewer.',
                ])
                ->confidence(1.0)
                ->expand($this->expansionFor($reviewed));
        }

        try {
            $classification = $this->classifier->classify(
                ocrText: $context->artifact('ocr_text')->text(),
                hint: $receipt->doc_type_hint,
            );
        } catch (AiException $exception) {
            return StepResult::failure(
                $exception->isRetryable()
                    ? StepException::retryable($exception->getMessage(), $exception)
                    : StepException::permanent($exception->getMessage(), $exception),
            );
        }

        $result = StepResult::success()
            ->artifact('doc_type', [
                'value' => $classification->type->value,
                'confidence' => $classification->confidence,
                'reason' => $classification->rawResponse['reason'] ?? null,
            ])
            ->confidence($classification->confidence)
            ->cost(
                inputTokens: $classification->usage->inputTokens,
                outputTokens: $classification->usage->outputTokens,
                usdMicros: $classification->usage->costUsdMicros,
            );

        if ($receipt->doc_type_hint !== null
            && $receipt->doc_type_hint !== DocumentType::Unknown
            && $receipt->doc_type_hint !== $classification->type) {
            $result->finding(Finding::blocker('classification_conflict', context: [
                'hint' => $receipt->doc_type_hint->value,
                'detected' => $classification->type->value,
            ]));
        }

        if ($classification->type === DocumentType::Unknown || $classification->confidence < self::MIN_CONFIDENCE) {
            // No branch to take — the gate will ask. Expanding on a guess would
            // spend an expensive extraction call on the wrong schema.
            return $result->finding(Finding::blocker('classification_uncertain', context: [
                'detected' => $classification->type->value,
                'confidence' => $classification->confidence,
            ]));
        }

        return $result->expand($this->expansionFor($classification->type));
    }

    /**
     * A second attempt only happens after a human answered the very question
     * this step failed at, so their answer wins over another guess — and over
     * another paid call that has no new evidence to work from.
     */
    private function reviewerDecision(StepContext $context): ?DocumentType
    {
        $payload = $context->artifactOrNull('review_decision')?->json() ?? [];
        $value = $payload['values']['doc_type'] ?? null;
        $type = is_string($value) ? DocumentType::tryFrom($value) : null;

        return $type === DocumentType::Unknown ? null : $type;
    }

    /**
     * @return list<StepDefinition>
     */
    private function expansionFor(DocumentType $type): array
    {
        if ($type === DocumentType::UtilityBill) {
            return [
                StepDefinition::make(ExtractUtilityBillStep::class)->inStage('extract')
                    ->dependsOn('classify_document')->maxAttempts(3),
                StepDefinition::make(MatchProviderStep::class)->inStage('resolve')
                    ->dependsOn('extract_utility_bill'),
                StepDefinition::make(LinkSeriesStep::class)->inStage('resolve')
                    ->dependsOn('match_provider'),
                StepDefinition::make(ValidateTotalsStep::class)->inStage('validate')
                    ->dependsOn('extract_utility_bill'),
                StepDefinition::make(AnomalyCheckStep::class)->inStage('validate')
                    ->dependsOn('link_series')->allowFailure(),
                StepDefinition::make(DedupeContentStep::class)->inStage('validate')
                    ->dependsOn('match_provider'),
            ];
        }

        return [
            StepDefinition::make(ExtractReceiptStep::class)->inStage('extract')
                ->dependsOn('classify_document')->maxAttempts(3),
            StepDefinition::make(MatchMerchantStep::class)->inStage('resolve')
                ->dependsOn('extract_receipt'),
            StepDefinition::make(MatchLocationStep::class)->inStage('resolve')
                ->dependsOn('match_merchant'),
            StepDefinition::make(MatchProductsStep::class)->inStage('resolve')
                ->dependsOn('extract_receipt'),
            StepDefinition::make(ValidateTotalsStep::class)->inStage('validate')
                ->dependsOn('extract_receipt'),
            StepDefinition::make(DedupeContentStep::class)->inStage('validate')
                ->dependsOn('match_merchant'),
        ];
    }
}
