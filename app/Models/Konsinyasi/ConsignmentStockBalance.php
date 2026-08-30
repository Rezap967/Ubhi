<?php

namespace App\Models\Konsinyasi;

use App\Models\Products\Product;
use App\Models\Store;
use App\Models\Products\Supplier;
use App\Models\User;
use App\Models\Products\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentStockBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'consignment_item_id',
        'product_id',
        'supplier_id',
        'warehouse_id',
        'quantity_available',
        'quantity_sold',
        'quantity_returned',
        'last_movement_type',
        'last_reference_no',
        'last_changed_by',
        'last_changed_at',
    ];

    protected $casts = [
        'quantity_available' => 'decimal:2',
        'quantity_sold' => 'decimal:2',
        'quantity_returned' => 'decimal:2',
        'last_changed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function consignmentItem(): BelongsTo
    {
        return $this->belongsTo(ConsignmentItem::class);
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
        return $this->belongsTo(Warehouse::class);
    }

    public function lastChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_changed_by');
    }
}