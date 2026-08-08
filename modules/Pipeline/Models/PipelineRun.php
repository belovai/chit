<?php

declare(strict_types=1);

namespace Modules\Pipeline\Models;

use App\Traits\UsesHashId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Pipeline\Database\Factories\PipelineRunFactory;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $hash_id
 * @property int $owner_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $definition_key
 * @property int $definition_version
 * @property array<array-key, mixed> $stages
 * @property RunStatus $status
 * @property TriggerSource $trigger_source
 * @property int|null $retried_from_run_id
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property int $cost_usd_micros
 * @property Carbon|null $expires_at
 * @property array<array-key, mixed>|null $error_summary
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $ai_credential_id
 * @property-read Collection<int, PipelineArtifact> $artifacts
 * @property-read int|null $artifacts_count
 * @property-read User|null $owner
 * @property-read Collection<int, PipelineRunStep> $steps
 * @property-read int|null $steps_count
 * @property-read Model|null $subject
 *
 * @method static \Modules\Pipeline\Database\Factories\PipelineRunFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereAiCredentialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereCostUsdMicros($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereDefinitionKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereDefinitionVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereDurationMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereErrorSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereHashId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereQueuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereRetriedFromRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereStages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereSubjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereTriggerSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRun withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'owner_id',
    'ai_credential_id',
    'subject_type',
    'subject_id',
    'definition_key',
    'definition_version',
    'stages',
    'status',
    'trigger_source',
    'retried_from_run_id',
    'queued_at',
    'started_at',
    'finished_at',
    'duration_ms',
    'cost_usd_micros',
    'expires_at',
    'error_summary',
)]
final class PipelineRun extends Model
{
    /** @use HasFactory<PipelineRunFactory> */
    use HasFactory, SoftDeletes, UsesHashId;

    /**
     * @return HasMany<PipelineRunStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(PipelineRunStep::class, 'run_id')
            ->orderBy('stage_position')
            ->orderBy('position')
            ->orderBy('attempt');
    }

    /**
     * @return HasMany<PipelineArtifact, $this>
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(PipelineArtifact::class, 'run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The live view of the run: one row per step_key, the highest attempt.
     *
     * @return Collection<int, PipelineRunStep>
     */
    public function currentSteps(): Collection
    {
        /** @var Collection<int, PipelineRunStep> $current */
        $current = $this->steps()->get()
            ->groupBy('step_key')
            ->map(fn (Collection $attempts): PipelineRunStep => $attempts->sortByDesc('attempt')->firstOrFail())
            ->values();

        return $current;
    }

    public function resolveRouteBinding($value, $field = null): self
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('owner_id', auth()->id())
            ->firstOrFail();
    }

    protected static function newFactory(): PipelineRunFactory
    {
        return PipelineRunFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stages' => 'array',
            'status' => RunStatus::class,
            'trigger_source' => TriggerSource::class,
            'error_summary' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
