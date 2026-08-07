<?php

declare(strict_types=1);

namespace Modules\Receipt\Actions;

use Illuminate\Support\Arr;
use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Actions\ResumeRun;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\Receipt\Exceptions\ReceiptNotAwaitingReviewException;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Models\ReceiptCorrection;

final class ReviewReceipt
{
    public function __construct(private readonly ResumeRun $resumeRun) {}

    /**
     * @param  array<string, mixed>  $values  only the fields the user changed
     */
    public function approve(Receipt $receipt, array $values = []): Receipt
    {
        $run = $this->parkedRun($receipt);

        $this->recordCorrections($receipt, $values);

        $this->resumeRun->approve($run, [
            new PendingArtifact('review_decision', ArtifactKind::Json, [
                'decision' => 'approve',
                'values' => $values,
                'decided_at' => now()->toIso8601String(),
            ]),
        ], $this->reopenFor($run, $values));

        return $receipt->refresh();
    }

    public function reject(Receipt $receipt, ?string $note = null): Receipt
    {
        $run = $this->parkedRun($receipt);

        $this->resumeRun->reject($run, [
            new PendingArtifact('review_decision', ArtifactKind::Json, [
                'decision' => 'reject',
                'note' => $note,
                'decided_at' => now()->toIso8601String(),
            ]),
        ]);

        return $receipt->refresh();
    }

    /**
     * When the classifier could not decide, the run parked before it branched —
     * it has no extract step at all. The reviewer's answer is the classification
     * that was missing, so classify_document runs again to expand the right
     * branch, and the gate runs again to judge whatever that branch turns up.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function reopenFor(PipelineRun $run, array $values): array
    {
        $docType = $values['doc_type'] ?? null;

        if (!is_string($docType) || DocumentType::tryFrom($docType) === null) {
            return [];
        }

        $hasBranch = $run->currentSteps()
            ->contains(fn (PipelineRunStep $step): bool => $step->stage === 'extract');

        return $hasBranch ? [] : ['classify_document', 'review_gate'];
    }

    private function parkedRun(Receipt $receipt): PipelineRun
    {
        $run = $receipt->currentRun;

        if ($run === null || $run->status !== RunStatus::AwaitingManual) {
            throw ReceiptNotAwaitingReviewException::for($receipt);
        }

        return $run;
    }

    /**
     * Every corrected field becomes a row. Nothing reads this table yet — it is
     * being filled now so that merchant-specific few-shot examples are possible
     * later; six months of corrections cannot be reconstructed after the fact.
     *
     * @param  array<string, mixed>  $values
     */
    private function recordCorrections(Receipt $receipt, array $values): void
    {
        $extracted = $this->extractedPayload($receipt);
        $merchantId = $receipt->currentRun?->artifacts()
            ->where('key', 'merchant_candidates')->whereNull('superseded_at')
            ->first()?->payload['accepted_id'] ?? null;

        foreach ($values as $field => $corrected) {
            $original = Arr::get($extracted, $field);

            if ($original === $corrected) {
                continue;
            }

            ReceiptCorrection::query()->create([
                'owner_id' => $receipt->owner_id,
                'receipt_id' => $receipt->id,
                'run_id' => $receipt->current_run_id,
                'merchant_id' => is_numeric($merchantId) ? (int) $merchantId : null,
                'doc_type' => $receipt->doc_type?->value,
                'field_path' => (string) $field,
                'ai_value' => ['value' => $original],
                'corrected_value' => ['value' => $corrected],
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractedPayload(Receipt $receipt): array
    {
        $artifact = $receipt->currentRun?->artifacts()
            ->whereIn('key', ['extracted_receipt', 'extracted_bill'])
            ->whereNull('superseded_at')
            ->first();

        /** @var array<string, mixed> $payload */
        $payload = $artifact?->payload['payload'] ?? [];

        return $payload;
    }
}
