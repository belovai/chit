<?php

declare(strict_types=1);

namespace Modules\Receipt\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Receipt\Database\Factories\ReceiptCorrectionFactory;

/**
 * @property int $id
 * @property int $owner_id
 * @property int $receipt_id
 * @property int|null $run_id
 * @property int|null $merchant_id
 * @property string|null $doc_type
 * @property string $field_path
 * @property array<array-key, mixed>|null $ai_value
 * @property array<array-key, mixed>|null $corrected_value
 * @property Carbon|null $created_at
 * @property-read Receipt|null $receipt
 *
 * @method static \Modules\Receipt\Database\Factories\ReceiptCorrectionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereAiValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereCorrectedValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereDocType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereFieldPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereReceiptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceiptCorrection whereRunId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'owner_id',
    'receipt_id',
    'run_id',
    'merchant_id',
    'doc_type',
    'field_path',
    'ai_value',
    'corrected_value',
)]
final class ReceiptCorrection extends Model
{
    /** @use HasFactory<ReceiptCorrectionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    protected static function newFactory(): ReceiptCorrectionFactory
    {
        return ReceiptCorrectionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_value' => 'array',
            'corrected_value' => 'array',
        ];
    }
}
