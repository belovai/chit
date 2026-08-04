<?php

declare(strict_types=1);

namespace Modules\Pipeline\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Pipeline\Models\PipelineArtifact;

/**
 * Emitted whenever the engine writes an artifact. Domain modules project
 * scalar fields cached on their own entities from this instead of writing
 * them from inside a step, keeping every such cache column a
 * single-writer projection — the same discipline RunStatusChanged gives
 * receipts.status.
 */
final class ArtifactPublished
{
    use Dispatchable;

    public function __construct(
        public readonly PipelineArtifact $artifact,
    ) {}
}
