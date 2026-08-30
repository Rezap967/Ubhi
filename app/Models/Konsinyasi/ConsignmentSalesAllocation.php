<?php
namespace App\Models\Konsinyasi;

use App\Models\Kasir\PosTransaction;
use App\Models\Kasir\PosTransactionItem;
use App\Models\Store;
use App\Models\Products\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentSalesAllocation extends Model
{
    protected $table = 'consignment_sales_allocations';

    protected $fillable = [
        'store_id',
        'sale_id',
        'sale_item_id',
        'consignment_item_id',
        'supplier_id',
        'quantity_sold',
        'selling_price',
        'gross_amount',
        'supplier_amount',
        'store_margin',
        'settlement_status',
        'settlement_id',
        'settled_at',
    ];

    protected $casts = [
        'quantity_sold' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'supplier_amount' => 'decimal:2',
        'store_margin' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class, 'sale_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PosTransactionItem::class, 'sale_item_id');
    }

    public function consignmentItem(): BelongsTo
    {
        return $this->belongsTo(ConsignmentItem::class, 'consignment_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function transaction()
{
    return $this->belongsTo(
        \App\Models\Kasir\PosTransaction::class, // sesuaikan namespace model POS
        'sale_id' // atau 'pos_transaction_id' — pakai nama kolom yang BENAR di tabel
    );
}
}