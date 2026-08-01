<?php

declare(strict_types=1);

namespace Modules\Pipeline\ValueObjects;

use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\StepOutcome;
use Throwable;

final class StepResult
{
    /** @var list<PendingArtifact> */
    private array $artifacts = [];

    /** @var list<Finding> */
    private array $findings = [];

    /** @var list<StepDefinition> */
    private array $expansions = [];

    private ?float $confidence = null;

    private ?int $inputTokens = null;

    private ?int $outputTokens = null;

    private ?int $costUsdMicros = null;

    private ?Throwable $exception = null;

    private ?string $skipReason = null;

    private function __construct(private readonly StepOutcome $outcome) {}

    public static function success(): self
    {
        return new self(StepOutcome::Success);
    }

    public static function failure(Throwable $exception): self
    {
        $result = new self(StepOutcome::Failure);
        $result->exception = $exception;

        return $result;
    }

    public static function skipped(string $reason): self
    {
        $result = new self(StepOutcome::Skipped);
        $result->skipReason = $reason;

        return $result;
    }

    /** The step finished its work but the run must pause for a human decision. */
    public static function hold(): self
    {
        return new self(StepOutcome::Hold);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function artifact(string $key, array $payload): self
    {
        $this->artifacts[] = new PendingArtifact($key, ArtifactKind::Json, $payload);

        return $this;
    }

    public function textArtifact(string $key, string $text): self
    {
        $this->artifacts[] = new PendingArtifact($key, ArtifactKind::Text, ['text' => $text]);

        return $this;
    }

    public function binaryArtifact(
        string $key,
        string $disk,
        string $path,
        ?string $mime = null,
        ?int $sizeBytes = null,
        ?string $checksum = null,
    ): self {
        $this->artifacts[] = new PendingArtifact(
            key: $key,
            kind: ArtifactKind::Binary,
            disk: $disk,
            path: $path,
            mime: $mime,
            sizeBytes: $sizeBytes,
            checksum: $checksum,
        );

        return $this;
    }

    public function confidence(float $confidence): self
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function finding(Finding $finding): self
    {
        $this->findings[] = $finding;

        return $this;
    }

    public function cost(?int $inputTokens = null, ?int $outputTokens = null, ?int $usdMicros = null): self
    {
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
        $this->costUsdMicros = $usdMicros;

        return $this;
    }

    /**
     * @param  list<StepDefinition>  $definitions
     */
    public function expand(array $definitions): self
    {
        $this->expansions = [...$this->expansions, ...$definitions];

        return $this;
    }

    public function outcome(): StepOutcome
    {
        return $this->outcome;
    }

    /**
     * @return list<PendingArtifact>
     */
    public function artifacts(): array
    {
        return $this->artifacts;
    }

    /**
     * @return list<Finding>
     */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * @return list<StepDefinition>
     */
    public function expansions(): array
    {
        return $this->expansions;
    }

    public function confidenceValue(): ?float
    {
        return $this->confidence;
    }

    public function inputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function outputTokens(): ?int
    {
        return $this->outputTokens;
    }

    public function costUsdMicros(): ?int
    {
        return $this->costUsdMicros;
    }

    public function exception(): ?Throwable
    {
        return $this->exception;
    }

    public function skipReason(): ?string
    {
        return $this->skipReason;
    }
}
