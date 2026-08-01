<?php

declare(strict_types=1);

namespace Modules\Pipeline\Enums;

enum StepOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Skipped = 'skipped';
    /** The step completed but the run must pause for a human decision. */
    case Hold = 'hold';
}
