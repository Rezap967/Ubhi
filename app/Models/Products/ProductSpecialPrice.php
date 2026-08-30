<?php

// app/Models/Products/ProductSpecialPrice.php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecialPrice extends Model
{
    protected $fillable = [
        'product_id',
        'unit_id',
        'min_qty',
        'max_qty',
        'special_price',
        'customer_group_id',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'min_qty' => 'decimal:2',
        'max_qty' => 'decimal:2',
        'special_price' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** Table tidak memiliki updated_at. */
    const UPDATED_AT = null;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }
}
