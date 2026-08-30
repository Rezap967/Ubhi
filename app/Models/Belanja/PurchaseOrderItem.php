<?php

namespace App\Models\Belanja;

use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'qty_ordered',
        'qty_received',
        'unit_price',
        'subtotal',
        'expiry_date',
    ];

    protected $casts = [
        'qty_ordered' => 'integer',
        'qty_received' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'expiry_date' => 'date:Y-m-d',
    ];

    protected $appends = [
        'qty_pending',
        'subtotal_formatted',
    ];

    public function getQtyPendingAttribute(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return 'Rp '.number_format((float) $this->subtotal, 0, ',', '.');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
