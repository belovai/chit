<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;
use Modules\Receipt\Services\GatePolicy;

/**
 * Collects every finding the run produced, asks the policy, and either lets the
 * run commit unattended or parks it with a review_request describing exactly
 * what is uncertain — so the review screen asks about those fields and no others.
 */
final class ReviewGateStep implements PipelineStep
{
    public function __construct(private readonly GatePolicy $policy) {}

    public static function key(): string
    {
        return 'review_gate';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $receipt = ArtifactCodec::subject($context)->refresh();
        $type = $receipt->doc_type ?? DocumentType::Unknown;

        $findings = $this->collectFindings($receipt->current_run_id);
        $decision = $this->policy->evaluate($findings, $this->extractionConfidence($context), $type);

        if ($decision->passes) {
            return StepResult::success();
        }

        return StepResult::hold()->artifact('review_request', [
            'doc_type' => $type->value,
            'reason' => $decision->reason,
            'blockers' => $decision->blockers,
            'warnings' => $decision->warnings,
            'findings' => $findings,
            // The fields the review UI should highlight, derived from the codes
            // that actually fired rather than from a fixed list.
            'fields' => $this->fieldsFor([...$decision->blockers, ...$decision->warnings]),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectFindings(?int $runId): array
    {
        if ($runId === null) {
            return [];
        }

        $findings = [];

        foreach (PipelineRunStep::query()->where('run_id', $runId)->get() as $step) {
            foreach ($step->findings ?? [] as $finding) {
                $findings[] = [...$finding, 'step_key' => $step->step_key];
            }
        }

        return $findings;
    }

    private function extractionConfidence(StepContext $context): ?float
    {
        $artifact = $context->artifactOrNull('extracted_receipt') ?? $context->artifactOrNull('extracted_bill');

        if ($artifact === null) {
            return null;
        }

        $confidence = $artifact->json()['confidence'] ?? null;

        return is_numeric($confidence) ? (float) $confidence : null;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function fieldsFor(array $codes): array
    {
        $map = [
            'total_missing' => ['total_minor'],
            'line_items_sum_mismatch' => ['total_minor', 'items'],
            'date_in_future' => ['occurred_at'],
            'merchant_ambiguous' => ['merchant'],
            'new_merchant' => ['merchant'],
            'possible_duplicate' => ['occurred_at', 'total_minor'],
            'exact_duplicate' => [],
            'classification_uncertain' => ['doc_type'],
            'classification_conflict' => ['doc_type'],
            'meter_reading_decreased' => ['meter_reading'],
            'consumption_anomaly' => ['consumption'],
            'period_gap' => ['period_start', 'period_end'],
            'low_ocr_confidence' => [],
        ];

        $fields = [];

        foreach ($codes as $code) {
            foreach ($map[$code] ?? [] as $field) {
                $fields[$field] = true;
            }
        }

        return array_keys($fields);
    }
}
