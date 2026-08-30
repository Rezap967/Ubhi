<?php

namespace App\Models\Stock;

use App\Models\Products\Product;
use App\Models\Products\Supplier;
use App\Models\Products\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockIn extends Model
{
    protected $table = 'stock_ins';

    protected $fillable = [
        'stock_in_code',
        'store_id',
        'product_id',
        'warehouse_id',
        'supplier_id',
        'quantity',
        'purchase_price',
        'payment_status',
        'reference_no',
        'received_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'purchase_price' => 'float',
        'received_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
