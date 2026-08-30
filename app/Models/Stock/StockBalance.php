<?php

namespace App\Models\Stock;

use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'last_movement_type',
        'last_reference_no',
        'last_quantity_change',
        'last_changed_by',
        'last_changed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'last_quantity_change' => 'decimal:2',
        'last_changed_at' => 'datetime',
    ];

    /**
     * Store pemilik stok.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Produk yang memiliki stok.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Gudang tempat stok berada.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * User yang terakhir mengubah stok.
     */
    public function lastChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_changed_by');
    }
}
