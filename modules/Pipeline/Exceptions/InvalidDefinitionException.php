<?php

declare(strict_types=1);

namespace Modules\Pipeline\Exceptions;

use RuntimeException;

final class InvalidDefinitionException extends RuntimeException
{
    public static function unknownStage(string $stepKey, string $stage): self
    {
        return new self("Step [{$stepKey}] targets stage [{$stage}], which the definition does not declare.");
    }

    public static function unknownDependency(string $stepKey, string $dependency): self
    {
        return new self("Step [{$stepKey}] depends on [{$dependency}], which is not part of this run.");
    }

    public static function duplicateStepKey(string $stepKey): self
    {
        return new self("Step key [{$stepKey}] appears more than once in this run.");
    }
}
