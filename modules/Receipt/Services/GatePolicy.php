<?php

declare(strict_types=1);

namespace Modules\Receipt\Services;

use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Enums\FindingSeverity;

final readonly class GateDecision
{
    /**
     * @param  list<string>  $blockers
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $passes,
        public array $blockers,
        public array $warnings,
        public ?string $reason = null,
    ) {}
}

/**
 * The single place that decides whether a run may finish unattended.
 *
 * Steps report what they saw; this decides what it means. Keeping the judgement
 * here — and its inputs in config — is what lets the review burden be tightened
 * or relaxed without touching a step.
 */
final class GatePolicy
{
    /**
     * @param  list<array<string, mixed>>  $findings
     */
    public function evaluate(array $findings, ?float $confidence, DocumentType $type): GateDecision
    {
        /** @var array<string, string> $severities */
        $severities = config('receipt.gate.severity', []);

        $threshold = (float) (config('receipt.gate.min_confidence')[$type->value] ?? 0.0);

        // Some warnings describe how the reading went, not what was read. OCR
        // is a preprocessor here — when the extraction itself came back
        // confident enough to commit unattended, unreadable OCR text says
        // nothing about the document and must not park the run on its own.
        //
        // A type with no threshold of its own (`unknown`) falls back to 0.0,
        // which every confidence clears — that proves nothing, so it does not
        // count as confident. Waiving must be earned against a real bar,
        // least of all on a document we could not even classify.
        $isConfident = $confidence !== null && $threshold > 0.0 && $confidence >= $threshold;
        /** @var list<string> $waivable */
        $waivable = config('receipt.gate.waived_when_confident', []);

        $blockers = [];
        $warnings = [];

        foreach ($findings as $finding) {
            $code = (string) ($finding['code'] ?? '');

            if ($code === '') {
                continue;
            }

            // Config is authoritative — a step's proposed severity is a hint.
            // An unknown code defaults to `warning`: something new happened and
            // that is precisely when a human should look.
            $severity = FindingSeverity::tryFrom($severities[$code] ?? 'warning') ?? FindingSeverity::Warning;

            // Warnings only: the two config tables are edited independently, and
            // a code listed as waivable while marked a blocker must keep
            // blocking rather than vanish from the decision without a trace.
            if ($isConfident && $severity === FindingSeverity::Warning && in_array($code, $waivable, true)) {
                continue;
            }

            match ($severity) {
                FindingSeverity::Blocker => $blockers[] = $code,
                FindingSeverity::Warning => $warnings[] = $code,
                FindingSeverity::Info => null,
            };
        }

        if ($blockers !== []) {
            return new GateDecision(false, $blockers, $warnings, 'blocker');
        }

        if (count($warnings) > (int) config('receipt.gate.max_warnings')) {
            return new GateDecision(false, $blockers, $warnings, 'too_many_warnings');
        }

        // Not the negation of `$isConfident`: an unset threshold stops nothing
        // here, it only withholds the benefit of the doubt above.
        if ($confidence !== null && $confidence < $threshold) {
            return new GateDecision(false, $blockers, $warnings, 'low_confidence');
        }

        return new GateDecision(true, $blockers, $warnings);
    }
}
