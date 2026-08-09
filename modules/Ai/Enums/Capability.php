<?php

declare(strict_types=1);

namespace Modules\Ai\Enums;

use App\Traits\EnumCompares;

enum Capability: string
{
    use EnumCompares;

    case Vision = 'vision';
    case JsonSchema = 'json_schema';
    case PromptCache = 'prompt_cache';
}
