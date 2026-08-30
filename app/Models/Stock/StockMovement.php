<?php

namespace App\Models\Stock;

use App\Models\Products\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'store_id',
        'product_id',
        'reference_type',
        'reference_id',
        'movement_type',
        'quantity',
        'before_quantity',
        'after_quantity',
        'notes',
        'movement_at',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'before_quantity' => 'decimal:3',
        'after_quantity' => 'decimal:3',
        'movement_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
