<?php

namespace App\Models\Belanja;

use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_return_id',
        'purchase_order_item_id',
        'product_id',
        'qty_returned',
        'unit_price',
        'subtotal',
        'reason',
        'status_item',
    ];

    protected $casts = [
        'qty_returned' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected $appends = [
        'subtotal_formatted',
    ];

    public function getSubtotalFormattedAttribute(): string
    {
        return 'Rp '.number_format((float) $this->subtotal, 0, ',', '.');
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
