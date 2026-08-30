<?php

namespace App\Models\Stock;

use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOut extends Model
{
    protected $table = 'stock_outs';

    protected $fillable = [
        'stock_out_code',
        'store_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'reason',
        'reference_no',
        'out_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'out_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
