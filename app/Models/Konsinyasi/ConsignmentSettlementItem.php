<?php

namespace App\Models\Konsinyasi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentSettlementItem extends Model
{
    protected $table = 'consignment_settlement_items';

    protected $fillable = [
        'settlement_id', 'sales_allocation_id', 'quantity_sold',
        'gross_amount', 'supplier_amount', 'store_margin'
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ConsignmentSettlement::class, 'settlement_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ConsignmentSalesAllocation::class, 'sales_allocation_id');
    }
}