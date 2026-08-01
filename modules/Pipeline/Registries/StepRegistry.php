<?php

declare(strict_types=1);

namespace Modules\Pipeline\Registries;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\UnknownStepException;

/**
 * Key -> step class map. This is how the engine stays free of domain imports:
 * domain modules push their classes in from their own service provider.
 */
final class StepRegistry
{
    /** @var array<string, class-string<PipelineStep>> */
    private array $steps = [];

    /**
     * @param  class-string<PipelineStep>  $stepClass
     */
    public function register(string $stepClass): void
    {
        $this->steps[$stepClass::key()] = $stepClass;
    }

    public function has(string $key): bool
    {
        return isset($this->steps[$key]);
    }

    /**
     * @return class-string<PipelineStep>
     */
    public function classFor(string $key): string
    {
        return $this->steps[$key] ?? throw UnknownStepException::forKey($key);
    }

    public function resolve(string $key): PipelineStep
    {
        return app($this->classFor($key));
    }
}
