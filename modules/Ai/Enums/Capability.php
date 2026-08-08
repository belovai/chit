<?php

declare(strict_types=1);

namespace Modules\Ai\Enums;

enum Capability: string
{
    case Vision = 'vision';
    case JsonSchema = 'json_schema';
    case PromptCache = 'prompt_cache';
}
