<?php

declare(strict_types=1);

namespace Modules\Ai\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Ai\Database\Factories\AiUsageLogFactory;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $owner_id
 * @property int|null $ai_credential_id
 * @property string $provider
 * @property string $model
 * @property string $purpose
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cached_input_tokens
 * @property int $cost_usd_micros
 * @property Carbon|null $created_at
 * @property-read AiCredential|null $credential
 * @property-read User|null $owner
 * @property-read Model|null $subject
 *
 * @method static \Modules\Ai\Database\Factories\AiUsageLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereAiCredentialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereCachedInputTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereCostUsdMicros($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereInputTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereOutputTokens($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog wherePurpose($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUsageLog whereSubjectType($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'owner_id',
    'ai_credential_id',
    'provider',
    'model',
    'purpose',
    'subject_type',
    'subject_id',
    'input_tokens',
    'output_tokens',
    'cached_input_tokens',
    'cost_usd_micros',
    // Mass-assignable on purpose: factories go through fill(), so without this
    // a `->create(['created_at' => ...])` is silently dropped and every seeded
    // row lands on now() — which would make the usage-window tests meaningless.
    'created_at',
)]
final class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function newFactory(): AiUsageLogFactory
    {
        return AiUsageLogFactory::new();
    }

    /**
     * @return BelongsTo<AiCredential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(AiCredential::class, 'ai_credential_id');
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
}
