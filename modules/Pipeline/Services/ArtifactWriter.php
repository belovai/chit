<?php

declare(strict_types=1);

namespace Modules\Pipeline\Services;

use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Events\ArtifactPublished;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\ValueObjects\Artifact;
use Modules\Pipeline\ValueObjects\PendingArtifact;

/**
 * Artifacts are immutable. Writing a key that already exists supersedes the old
 * row instead of updating it, so a previous attempt stays inspectable and
 * comparable against the new one.
 */
final class ArtifactWriter
{
    public function write(PipelineRunStep $step, PendingArtifact $pending): PipelineArtifact
    {
        PipelineArtifact::query()
            ->where('run_id', $step->run_id)
            ->where('key', $pending->key)
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);

        $created = PipelineArtifact::query()->create([
            'run_id' => $step->run_id,
            'step_id' => $step->id,
            'key' => $pending->key,
            'kind' => $pending->kind,
            'payload' => $pending->payload,
            'disk' => $pending->disk,
            'path' => $pending->path,
            'mime' => $pending->mime,
            'size_bytes' => $pending->sizeBytes,
            'checksum' => $pending->checksum,
        ]);

        ArtifactPublished::dispatch($created);

        return $created;
    }

    /**
     * Every live artifact of the run, keyed by artifact key.
     *
     * @return array<string, Artifact>
     */
    public function liveFor(PipelineRun $run): array
    {
        $live = [];

        foreach ($run->artifacts()->whereNull('superseded_at')->get() as $model) {
            $live[$model->key] = Artifact::fromModel($model);
        }

        return $live;
    }

    /** Stamps a prune deadline on the run's binary artifacts. Structured ones never expire. */
    public function scheduleBinaryExpiry(PipelineRun $run, int $retentionDays): void
    {
        $run->artifacts()
            ->where('kind', ArtifactKind::Binary->value)
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays($retentionDays)]);
    }
}
