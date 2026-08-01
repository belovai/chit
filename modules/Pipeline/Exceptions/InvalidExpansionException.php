<?php

declare(strict_types=1);

namespace Modules\Pipeline\Exceptions;

use RuntimeException;

final class InvalidExpansionException extends RuntimeException
{
    public static function unknownStage(string $stepKey, string $stage): self
    {
        return new self("Cannot add step [{$stepKey}]: the run declares no stage [{$stage}].");
    }

    public static function duplicateStepKey(string $stepKey): self
    {
        return new self("Cannot add step [{$stepKey}]: that key is already present in this run.");
    }

    public static function unknownDependency(string $stepKey, string $dependency): self
    {
        return new self("Cannot add step [{$stepKey}]: it depends on [{$dependency}], which is not in this run.");
    }

    /**
     * @param  list<string>  $keys
     */
    public static function cycle(array $keys): self
    {
        return new self('Cannot expand the run: the result would contain a dependency cycle ('.implode(' -> ', $keys).').');
    }
}
