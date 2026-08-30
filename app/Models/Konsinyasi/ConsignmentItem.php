<?php

namespace App\Models\Konsinyasi;

use App\Models\Products\Product;
use App\Models\Products\Supplier;
use App\Models\Products\Warehouse;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsignmentItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'product_id',
        'supplier_id',
        'default_warehouse_id',
        'selling_price',
        'consignment_price',
        'commission_type',
        'commission_value',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'consignment_price' => 'decimal:2',
        'commission_value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'quantity_available',
        'quantity_sold',
        'quantity_returned',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(ConsignmentStockBalance::class, 'consignment_item_id');
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(ConsignmentStockLedger::class, 'consignment_item_id');
    }

    public function getQuantityAvailableAttribute(): float
    {
        return (float) $this->stockBalances->sum('quantity_available');
    }

    public function getQuantitySoldAttribute(): float
    {
        return (float) $this->stockBalances->sum('quantity_sold');
    }

    public function getQuantityReturnedAttribute(): float
    {
        return (float) $this->stockBalances->sum('quantity_returned');
    }

    /**
     * Alias lama, jaga kompatibilitas jika masih ada pemanggilan available_stock.
     */
    public function getAvailableStockAttribute(): float
    {
        return $this->quantity_available;
    }

    public function calculateCommission(): float
    {
        return match ($this->commission_type) {
            'margin' => (float) $this->selling_price - (float) $this->consignment_price,
            'percentage' => (float) $this->selling_price * ((float) $this->commission_value / 100),
            'fixed' => (float) $this->commission_value,
            default => 0.0,
        };
    }
}