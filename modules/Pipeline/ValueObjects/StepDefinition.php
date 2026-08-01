<?php

declare(strict_types=1);

namespace Modules\Pipeline\ValueObjects;

use Modules\Pipeline\Contracts\PipelineStep;

final class StepDefinition
{
    /** @var list<string> */
    private array $dependsOn = [];

    private string $stage = '';

    private bool $allowFailure = false;

    private bool $isGate = false;

    private int $maxAttempts = 1;

    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * @param  class-string<PipelineStep>  $stepClass
     */
    private function __construct(private readonly string $stepClass) {}

    /**
     * @param  class-string<PipelineStep>  $stepClass
     */
    public static function make(string $stepClass): self
    {
        return new self($stepClass);
    }

    public function inStage(string $stage): self
    {
        $this->stage = $stage;

        return $this;
    }

    public function dependsOn(string ...$stepKeys): self
    {
        $this->dependsOn = [...$this->dependsOn, ...array_values($stepKeys)];

        return $this;
    }

    public function allowFailure(bool $allowFailure = true): self
    {
        $this->allowFailure = $allowFailure;

        return $this;
    }

    public function asGate(): self
    {
        $this->isGate = true;

        return $this;
    }

    public function maxAttempts(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function key(): string
    {
        return $this->stepClass::key();
    }

    /**
     * @return class-string<PipelineStep>
     */
    public function stepClass(): string
    {
        return $this->stepClass;
    }

    public function stage(): string
    {
        return $this->stage;
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array
    {
        return $this->dependsOn;
    }

    public function isAllowFailure(): bool
    {
        return $this->allowFailure;
    }

    public function isGate(): bool
    {
        return $this->isGate;
    }

    public function attempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }
}
