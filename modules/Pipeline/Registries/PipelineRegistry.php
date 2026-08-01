<?php

declare(strict_types=1);

namespace Modules\Pipeline\Registries;

use Modules\Pipeline\Definitions\PipelineDefinition;
use Modules\Pipeline\Exceptions\UnknownPipelineException;

final class PipelineRegistry
{
    /** @var array<string, PipelineDefinition> */
    private array $definitions = [];

    public function register(PipelineDefinition $definition): void
    {
        $this->definitions[$definition->key()] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): PipelineDefinition
    {
        return $this->definitions[$key] ?? throw UnknownPipelineException::forKey($key);
    }
}
