<?php

declare(strict_types=1);

namespace Modules\Pipeline\ValueObjects;

use Modules\Pipeline\Enums\StepStatus;

/** Flat, DB-free view of one current-attempt step row, for the resolver. */
final readonly class StepSnapshot
{
    /**
     * @param  list<string>  $dependsOn
     */
    public function __construct(
        public int $id,
        public string $stepKey,
        public int $stagePosition,
        public StepStatus $status,
        public array $dependsOn = [],
        public bool $allowFailure = false,
    ) {}
}
