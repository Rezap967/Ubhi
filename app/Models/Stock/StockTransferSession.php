<?php

namespace App\Models\Stock;

use App\Models\Products\Warehouse;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'transfer_code',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
        'status',
        'notes',
        'cancel_reason',
        'total_items',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date:Y-m-d',
        'total_items' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'transfer_id');
    }
}
