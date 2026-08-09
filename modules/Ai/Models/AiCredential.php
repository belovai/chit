<?php

declare(strict_types=1);

namespace Modules\Ai\Models;

use App\Traits\UsesHashId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Ai\Database\Factories\AiCredentialFactory;
use Modules\Ai\Enums\CredentialStatus;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $hash_id
 * @property int $owner_id
 * @property string $provider
 * @property string $label
 * @property string $api_key
 * @property string $key_last_four
 * @property string $key_fingerprint
 * @property string $model
 * @property array<array-key, mixed> $settings
 * @property bool $is_active
 * @property CredentialStatus $status
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $last_used_at
 * @property string|null $last_error
 * @property int $failure_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $owner
 * @property-read Collection<int, AiUsageLog> $usageLogs
 * @property-read int|null $usage_logs_count
 *
 * @method static Builder<static>|AiCredential active()
 * @method static \Modules\Ai\Database\Factories\AiCredentialFactory factory($count = null, $state = [])
 * @method static Builder<static>|AiCredential forUser(int $userId)
 * @method static Builder<static>|AiCredential newModelQuery()
 * @method static Builder<static>|AiCredential newQuery()
 * @method static Builder<static>|AiCredential query()
 * @method static Builder<static>|AiCredential whereApiKey($value)
 * @method static Builder<static>|AiCredential whereCreatedAt($value)
 * @method static Builder<static>|AiCredential whereFailureCount($value)
 * @method static Builder<static>|AiCredential whereHashId($value)
 * @method static Builder<static>|AiCredential whereId($value)
 * @method static Builder<static>|AiCredential whereIsActive($value)
 * @method static Builder<static>|AiCredential whereKeyFingerprint($value)
 * @method static Builder<static>|AiCredential whereKeyLastFour($value)
 * @method static Builder<static>|AiCredential whereLabel($value)
 * @method static Builder<static>|AiCredential whereLastError($value)
 * @method static Builder<static>|AiCredential whereLastUsedAt($value)
 * @method static Builder<static>|AiCredential whereLastVerifiedAt($value)
 * @method static Builder<static>|AiCredential whereModel($value)
 * @method static Builder<static>|AiCredential whereOwnerId($value)
 * @method static Builder<static>|AiCredential whereProvider($value)
 * @method static Builder<static>|AiCredential whereSettings($value)
 * @method static Builder<static>|AiCredential whereStatus($value)
 * @method static Builder<static>|AiCredential whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'owner_id',
    'provider',
    'label',
    'api_key',
    'key_last_four',
    'key_fingerprint',
    'model',
    'settings',
    'is_active',
    'status',
    'last_verified_at',
    'last_used_at',
    'last_error',
    'failure_count',
)]
// Hides the decrypted key from `toArray()`, so it cannot leak through a
// resource, a log line, or a `dd()` of the model.
#[Hidden('api_key', 'key_fingerprint')]
final class AiCredential extends Model
{
    /** @use HasFactory<AiCredentialFactory> */
    use HasFactory, UsesHashId;

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'settings' => 'array',
            'is_active' => 'bool',
            'status' => CredentialStatus::class,
            'last_verified_at' => 'datetime',
            'last_used_at' => 'datetime',
            'failure_count' => 'integer',
        ];
    }

    protected static function newFactory(): AiCredentialFactory
    {
        return AiCredentialFactory::new();
    }

    public static function fingerprint(#[\SensitiveParameter] string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }

    public static function lastFour(#[\SensitiveParameter] string $apiKey): string
    {
        return mb_substr($apiKey, -4);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<AiUsageLog, $this>
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class, 'ai_credential_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, int $userId): void
    {
        $query->where('owner_id', $userId);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function maskedKey(): string
    {
        return '••••'.$this->key_last_four;
    }
}
