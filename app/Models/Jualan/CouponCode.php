<?php

namespace App\Models\Jualan;

use App\Models\Customer;
use App\Models\Kasir\PosTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponCode extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'coupon_id',
        'used_by',
        'used_at',
        'pos_transaction_id',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────────────────────────

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'used_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class, 'pos_transaction_id');
    }
}
