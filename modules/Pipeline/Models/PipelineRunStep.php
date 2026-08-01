<?php

declare(strict_types=1);

namespace Modules\Pipeline\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Pipeline\Database\Factories\PipelineRunStepFactory;
use Modules\Pipeline\Enums\StepStatus;

/**
 * @property int $id
 * @property int $run_id
 * @property string $step_key
 * @property string $stage
 * @property int $stage_position
 * @property int $position
 * @property int $attempt
 * @property int $max_attempts
 * @property StepStatus $status
 * @property array<array-key, mixed> $depends_on
 * @property bool $allow_failure
 * @property bool $is_gate
 * @property array<array-key, mixed>|null $config
 * @property int|null $added_by_step_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property float|null $confidence
 * @property array<array-key, mixed>|null $findings
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property int|null $cost_usd_micros
 * @property array<array-key, mixed>|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PipelineArtifact> $artifacts
 * @property-read int|null $artifacts_count
 * @property-read PipelineRun|null $run
 *
 * @method static \Modules\Pipeline\Database\Factories\PipelineRunStepFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereAddedByStepId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereAllowFailure($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereAttempt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereConfidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereCostUsdMicros($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereDependsOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereDurationMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereFindings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereInputTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereIsGate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereMaxAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereOutputTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereStagePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereStepKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PipelineRunStep whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'run_id',
    'step_key',
    'stage',
    'stage_position',
    'position',
    'attempt',
    'max_attempts',
    'status',
    'depends_on',
    'allow_failure',
    'is_gate',
    'config',
    'added_by_step_id',
    'started_at',
    'finished_at',
    'duration_ms',
    'confidence',
    'findings',
    'input_tokens',
    'output_tokens',
    'cost_usd_micros',
    'error',
)]
final class PipelineRunStep extends Model
{
    /** @use HasFactory<PipelineRunStepFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PipelineRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class, 'run_id');
    }

    /**
     * @return HasMany<PipelineArtifact, $this>
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(PipelineArtifact::class, 'step_id');
    }

    protected static function newFactory(): PipelineRunStepFactory
    {
        return PipelineRunStepFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StepStatus::class,
            'depends_on' => 'array',
            'allow_failure' => 'boolean',
            'is_gate' => 'boolean',
            'config' => 'array',
            'findings' => 'array',
            'error' => 'array',
            'confidence' => 'float',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
