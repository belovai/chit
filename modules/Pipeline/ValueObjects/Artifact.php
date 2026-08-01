<?php

declare(strict_types=1);

namespace Modules\Pipeline\ValueObjects;

use Illuminate\Support\Facades\Storage;
use LogicException;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Models\PipelineArtifact;

/** Read-side view of a stored artifact, handed to steps via StepContext. */
final readonly class Artifact
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public string $key,
        public ArtifactKind $kind,
        public ?array $payload,
        public ?string $disk,
        public ?string $path,
    ) {}

    public static function fromModel(PipelineArtifact $model): self
    {
        return new self(
            key: $model->key,
            kind: $model->kind,
            payload: $model->payload,
            disk: $model->disk,
            path: $model->path,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->kind === ArtifactKind::Binary) {
            throw new LogicException("Artifact [{$this->key}] is binary; use contents().");
        }

        return $this->payload ?? [];
    }

    /** Text artifacts store their body under the `text` key of the jsonb payload. */
    public function text(): string
    {
        if ($this->kind !== ArtifactKind::Text) {
            throw new LogicException("Artifact [{$this->key}] is not a text artifact.");
        }

        return (string) ($this->payload['text'] ?? '');
    }

    public function contents(): string
    {
        if ($this->kind !== ArtifactKind::Binary) {
            throw new LogicException("Artifact [{$this->key}] is not a binary artifact.");
        }

        return (string) Storage::disk((string) $this->disk)->get((string) $this->path);
    }
}
