<?php

namespace App\Models\Konsinyasi;

use App\Models\Store;
use App\Models\User;
use App\Models\Products\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConsignmentStockLedger extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // immutable ledger
    // Override nama tabel default Eloquent
    protected $table = 'consignment_stock_ledger';

    protected $guarded = ['id'];
    protected $fillable = [
        'store_id',
        'consignment_item_id',
        'warehouse_id',
        'movement_type',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
        'reference_no',
        'created_by',
    ];

    protected $casts = [
        'quantity_change' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function consignmentItem(): BelongsTo
    {
        return $this->belongsTo(ConsignmentItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}