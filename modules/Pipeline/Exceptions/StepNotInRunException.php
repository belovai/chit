<?php

declare(strict_types=1);

namespace Modules\Pipeline\Exceptions;

use Modules\Pipeline\Models\PipelineRun;
use RuntimeException;

final class StepNotInRunException extends RuntimeException
{
    public static function for(PipelineRun $run, string $stepKey): self
    {
        return new self("Run [{$run->hash_id}] has no step [{$stepKey}].");
    }
}
