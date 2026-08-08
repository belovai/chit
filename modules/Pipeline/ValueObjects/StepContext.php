<?php

declare(strict_types=1);

namespace Modules\Pipeline\ValueObjects;

use Illuminate\Database\Eloquent\Model;
use Modules\Pipeline\Exceptions\ArtifactNotFoundException;

/**
 * Everything a step is allowed to see. Deliberately narrow: a step cannot
 * inspect the run's shape, its neighbours, or what comes next.
 */
final readonly class StepContext
{
    /**
     * @param  array<string, Artifact>  $artifacts  live artifacts keyed by artifact key
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private array $artifacts,
        private array $config,
        private int $ownerId,
        private ?int $aiCredentialId,
        private ?Model $subject,
        private int $attempt,
    ) {}

    public function artifact(string $key): Artifact
    {
        return $this->artifacts[$key] ?? throw ArtifactNotFoundException::forKey($key);
    }

    public function artifactOrNull(string $key): ?Artifact
    {
        return $this->artifacts[$key] ?? null;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function ownerId(): int
    {
        return $this->ownerId;
    }

    /**
     * The credential this run was started with. Opaque here — the Ai module
     * turns it into a usable connection.
     */
    public function aiCredentialId(): ?int
    {
        return $this->aiCredentialId;
    }

    public function subject(): ?Model
    {
        return $this->subject;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }
}
