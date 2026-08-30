<?php

namespace App\Models\Konsinyasi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Products\Supplier;
use App\Models\User;
use App\Models\Store;

class ConsignmentSettlement extends Model
{
    protected $table = 'consignment_settlements';

    protected $fillable = [
        'store_id', 'settlement_code', 'supplier_id', 'period_start', 'period_end',
        'gross_sales', 'supplier_payable', 'store_margin', 'payment_method',
        'payment_reference_no', 'status', 'approved_by', 'approved_at',
        'notes', 'created_by', 'request_uuid', 'journal_entry_id'
    ];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'period_end' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'gross_sales' => 'float',
        'supplier_payable' => 'float',
        'store_margin' => 'float',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentSettlementItem::class, 'settlement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}