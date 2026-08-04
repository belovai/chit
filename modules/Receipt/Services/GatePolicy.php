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

        $threshold = (float) (config('receipt.gate.min_confidence')[$type->value] ?? 0.0);

        if ($confidence !== null && $confidence < $threshold) {
            return new GateDecision(false, $blockers, $warnings, 'low_confidence');
        }

        return new GateDecision(true, $blockers, $warnings);
    }
}
