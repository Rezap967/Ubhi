<?php

namespace App\Models\Kasir;

use App\Models\Jualan\Coupon;
use App\Models\Jualan\Promotion;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosAppliedDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'cart_id',
        'promotion_id',
        'promotion_name',
        'coupon_id',
        'coupon_name',
        'scope',
        'discount_type',
        'discount_amount',
        'source',
        'status',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(PosCart::class, 'cart_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
