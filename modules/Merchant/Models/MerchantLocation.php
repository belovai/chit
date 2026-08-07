<?php

declare(strict_types=1);

namespace Modules\Merchant\Models;

use App\Traits\UsesHashId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Merchant\Database\Factories\MerchantLocationFactory;
use Modules\Merchant\Services\AddressNormalizer;

/**
 * @property int $id
 * @property string $hash_id
 * @property int $merchant_id
 * @property bool $is_online
 * @property string|null $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $normalized_address
 * @property-read Merchant|null $merchant
 *
 * @method static \Modules\Merchant\Database\Factories\MerchantLocationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereHashId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereIsOnline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereNormalizedAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantLocation withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'merchant_id',
    'is_online',
    'address',
    'latitude',
    'longitude',
)]
final class MerchantLocation extends Model
{
    /** @use HasFactory<MerchantLocationFactory> */
    use HasFactory, SoftDeletes, UsesHashId;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_online' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Writing the address also writes its comparison key. This lives on the
     * model rather than in the actions so that every write path — pipeline,
     * settings UI, factory, seeder — stays consistent by construction.
     *
     * @return Attribute<string|null, array{address: string|null, normalized_address: string|null}>
     */
    protected function address(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): array => [
                'address' => $value,
                'normalized_address' => AddressNormalizer::normalize($value),
            ],
        );
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function resolveRouteBinding($value, $field = null): self
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->whereHas('merchant', fn ($query) => $query->where('owner_id', auth()->id()))
            ->firstOrFail();
    }

    protected static function newFactory(): MerchantLocationFactory
    {
        return MerchantLocationFactory::new();
    }
}
