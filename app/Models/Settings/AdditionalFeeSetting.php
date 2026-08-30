<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Store;

class AdditionalFeeSetting extends Model
{
    use HasFactory;

    protected $table = 'additional_fee_settings';

    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENT = 'percent';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'store_id',
        'name',
        'fee_type',
        'amount',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: Filter by Store ID
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope: Filter Active Status Only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Filter Status (Nullable)
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (!empty($status)) {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope: Filter Fee Type (Nullable)
     */
    public function scopeFeeType(Builder $query, ?string $feeType): Builder
    {
        if (!empty($feeType)) {
            return $query->where('fee_type', $feeType);
        }
        return $query;
    }

    /**
     * Scope: Search by Name
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!empty($search)) {
            return $query->where('name', 'LIKE', '%' . $search . '%');
        }
        return $query;
    }

    /**
     * Relasi ke Model Store
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}