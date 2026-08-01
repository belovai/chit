<?php

declare(strict_types=1);

namespace Modules\Pipeline\Exceptions;

use Modules\Pipeline\Models\PipelineRun;
use RuntimeException;

final class RunNotAwaitingManualException extends RuntimeException
{
    public static function for(PipelineRun $run): self
    {
        return new self("Run [{$run->hash_id}] is [{$run->status->value}], not awaiting a manual decision.");
    }
}
