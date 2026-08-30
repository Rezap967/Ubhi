<?php

namespace App\Models\Belanja;

use App\Models\Products\Supplier;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'store_id',
        'status',
        'total',
        'notes',
        'jenis_pembayaran',
        'estimasi_tiba',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'estimasi_tiba' => 'date:Y-m-d',
    ];

    protected $appends = [
        'total_formatted',
    ];

    public function getTotalFormattedAttribute(): string
    {
        return 'Rp '.number_format((float) $this->total, 0, ',', '.');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope filter store.
     */
    public function scopeStoreScoped(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }
}
