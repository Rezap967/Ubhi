<?php

namespace App\Models\Kasir;

use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosCartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_name',
        'sku',
        'barcode',
        'unit_name',
        'unit_price',
        'quantity',
        'discount_value',
        'subtotal',
        'source',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:3',
        'discount_value' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(PosCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withDefault();
    }
}
