<?php

declare(strict_types=1);

namespace Modules\Pipeline\Contracts;

use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

/**
 * The single extension point of the engine. Domain modules implement this and
 * register the class with the StepRegistry; the engine only ever resolves by key.
 *
 * A step must be side-effect free outside the artifacts it declares — that is
 * what makes "rerun from this step" replayable.
 */
interface PipelineStep
{
    /** Stable machine key. Unique across the whole application. */
    public static function key(): string;

    /** Horizon queue name this step's job is dispatched to. */
    public static function queue(): string;

    public function handle(StepContext $context): StepResult;
}
