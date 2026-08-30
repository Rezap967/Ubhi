<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'tax_rates';

    protected $fillable = ['store_id', 'name', 'code', 'type', 'rate', 'effective_from', 'effective_until', 'is_system', 'is_active'];

    protected $casts = ['rate' => 'decimal:4', 'effective_from' => 'date', 'effective_until' => 'date', 'is_system' => 'boolean', 'is_active' => 'boolean'];

    /** @return HasMany<TaxRecord, $this> */
    public function taxRecords(): HasMany
    {
        return $this->hasMany(TaxRecord::class);
    }

    /** @param Builder<self> $query */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
