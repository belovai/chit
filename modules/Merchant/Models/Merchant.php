<?php

declare(strict_types=1);

namespace Modules\Merchant\Models;

use App\Traits\UsesHashId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Merchant\Database\Factories\MerchantFactory;
use Modules\User\Models\User;

/**
 * @property-read User|null $owner
 * @method static \Modules\Merchant\Database\Factories\MerchantFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant withoutTrashed()
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
