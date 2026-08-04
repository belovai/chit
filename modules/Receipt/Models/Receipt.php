<?php

declare(strict_types=1);

namespace Modules\Receipt\Models;

use App\Traits\UsesHashId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Receipt\Database\Factories\ReceiptFactory;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Transaction\Models\Transaction;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $hash_id
 * @property int $owner_id
 * @property string $original_filename
 * @property string $disk
 * @property string $path
 * @property string $file_hash
 * @property string $mime
 * @property int $size_bytes
 * @property DocumentType|null $doc_type
 * @property DocumentType|null $doc_type_hint
 * @property int|null $current_run_id
 * @property int|null $transaction_id
 * @property ReceiptStatus $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $series_key
 * @property-read Collection<int, ReceiptCorrection> $corrections
 * @property-read int|null $corrections_count
 * @property-read PipelineRun|null $currentRun
 * @property-read User|null $owner
 * @property-read Transaction|null $transaction
 *
 * @method static \Modules\Receipt\Database\Factories\ReceiptFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereCurrentRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereDocType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereDocTypeHint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereFileHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereHashId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereOriginalFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereSeriesKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Receipt withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable(
    'owner_id',
    'original_filename',
    'disk',
    'path',
    'file_hash',
    'mime',
    'size_bytes',
    'doc_type',
    'doc_type_hint',
    'series_key',
    'current_run_id',
    'transaction_id',
    'status',
)]
final class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory, SoftDeletes, UsesHashId;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<PipelineRun, $this>
     */
    public function currentRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class, 'current_run_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * @return HasMany<ReceiptCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(ReceiptCorrection::class, 'receipt_id');
    }

    public function resolveRouteBinding($value, $field = null): self
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('owner_id', auth()->id())
            ->firstOrFail();
    }

    protected static function newFactory(): ReceiptFactory
    {
        return ReceiptFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'doc_type' => DocumentType::class,
            'doc_type_hint' => DocumentType::class,
        ];
    }
}
