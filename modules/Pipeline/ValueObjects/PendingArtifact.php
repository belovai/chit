<?php

declare(strict_types=1);

namespace Modules\Pipeline\ValueObjects;

use Modules\Pipeline\Enums\ArtifactKind;

/** An artifact a step wants written. Becomes a `pipeline_artifacts` row. */
final readonly class PendingArtifact
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public string $key,
        public ArtifactKind $kind,
        public ?array $payload = null,
        public ?string $disk = null,
        public ?string $path = null,
        public ?string $mime = null,
        public ?int $sizeBytes = null,
        public ?string $checksum = null,
    ) {}
}
