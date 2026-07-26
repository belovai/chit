<?php

declare(strict_types=1);

namespace Modules\Merchant\Models;

use App\Traits\UsesHashId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Merchant\Database\Factories\MerchantFactory;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $hash_id
 * @property int $owner_id
 * @property string $name
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MerchantLocation> $locations
 * @property-read int|null $locations_count
 * @property-read User|null $owner
 *
 * @method static \Modules\Merchant\Database\Factories\MerchantFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereHashId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'owner_id',
    'name',
)]
final class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory, SoftDeletes, UsesHashId;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<MerchantLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(MerchantLocation::class);
    }

    public function resolveRouteBinding($value, $field = null): self
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('owner_id', auth()->id())
            ->firstOrFail();
    }

    protected static function newFactory(): MerchantFactory
    {
        return MerchantFactory::new();
    }
}
