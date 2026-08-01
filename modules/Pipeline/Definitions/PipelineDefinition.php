<?php

declare(strict_types=1);

namespace Modules\Pipeline\Definitions;

use Modules\Pipeline\ValueObjects\StepDefinition;

abstract class PipelineDefinition
{
    abstract public function key(): string;

    abstract public function version(): int;

    /**
     * Every stage the run may ever contain, in display order. Stages with no
     * initial steps are still listed — dynamic expansion fills them, and the UI
     * renders them as empty columns from the first render.
     *
     * @return list<string>
     */
    abstract public function stages(): array;

    /**
     * @return list<StepDefinition>
     */
    abstract public function steps(): array;
}
