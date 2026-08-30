<?php

namespace App\Models\Accounting;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $store_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string $normal_balance
 * @property string|null $parent_id
 * @property bool $is_system
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Store|null $store
 * @property-read Account|null $parent
 */
class Account extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'store_id',
        'code',
        'name',
        'description',
        'type',
        'normal_balance',
        'parent_id',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    /** @return HasMany<JournalEntryLine, $this> */
    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /** @return HasMany<JournalEntryLine, $this> */
    public function postedJournalEntryLines(): HasMany
    {
        return $this->journalEntryLines()->whereHas('journalEntry', function (Builder $query): void {
            $query->posted();
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
