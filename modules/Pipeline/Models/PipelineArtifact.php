<?php

declare(strict_types=1);

namespace Modules\Pipeline\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Pipeline\Database\Factories\PipelineArtifactFactory;
use Modules\Pipeline\Enums\ArtifactKind;

/**
 * @property int $id
 * @property int $run_id
 * @property int $step_id
 * @property string $key
 * @property ArtifactKind $kind
 * @property array<array-key, mixed>|null $payload
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $mime
 * @property int|null $size_bytes
 * @property string|null $checksum
 * @property Carbon|null $superseded_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PipelineRun|null $run
 * @property-read PipelineRunStep $step
 *
 * @method static \Modules\Pipeline\Database\Factories\PipelineArtifactFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereStepId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereSupersededAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineArtifact whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'run_id',
    'step_id',
    'key',
    'kind',
    'payload',
    'disk',
    'path',
    'mime',
    'size_bytes',
    'checksum',
    'superseded_at',
    'expires_at',
)]
final class PipelineArtifact extends Model
{
    /** @use HasFactory<PipelineArtifactFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PipelineRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class, 'run_id');
    }

    /**
     * @return BelongsTo<PipelineRunStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(PipelineRunStep::class, 'step_id');
    }

    protected static function newFactory(): PipelineArtifactFactory
    {
        return PipelineArtifactFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ArtifactKind::class,
            'payload' => 'array',
            'superseded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
