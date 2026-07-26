<?php

declare(strict_types=1);

namespace Modules\Transaction\Models;

use App\Traits\UsesHashId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Transaction\Database\Factories\TransactionFactory;
use Modules\Transaction\Enums\PaymentMethod;
use Modules\Transaction\Enums\TransactionSource;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $hash_id
 * @property int $owner_id
 * @property int $merchant_id
 * @property int|null $location_id
 * @property string $currency
 * @property TransactionSource $source
 * @property PaymentMethod $payment_method
 * @property numeric|null $discount_amount
 * @property numeric $total_amount
 * @property Carbon $occurred_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TransactionItem> $items
 * @property-read int|null $items_count
 * @property-read MerchantLocation|null $location
 * @property-read Merchant|null $merchant
 * @property-read User|null $owner
 *
 * @method static \Modules\Transaction\Database\Factories\TransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereHashId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'owner_id',
    'merchant_id',
    'location_id',
    'currency',
    'source',
    'payment_method',
    'discount_amount',
    'total_amount',
    'occurred_at',
)]
final class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes, UsesHashId;

    protected function casts(): array
    {
        return [
            'source' => TransactionSource::class,
            'payment_method' => PaymentMethod::class,
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'occurred_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class)->withTrashed();
    }

    /**
     * @return BelongsTo<MerchantLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(MerchantLocation::class, 'location_id')->withTrashed();
    }

    /**
     * @return HasMany<TransactionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class)->orderBy('id');
    }

    public function resolveRouteBinding($value, $field = null): self
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('owner_id', auth()->id())
            ->firstOrFail();
    }

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }
}
