<?php

namespace App\Models\Kasir;

use App\Models\Customer\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $store_id
 * @property int $session_id
 * @property int $user_id
 * @property ?int $customer_id
 * @property string $status
 * @property float $subtotal
 * @property ?float $subtotal_amount
 * @property float $discount_total
 * @property float $tax_total
 * @property float $grand_total
 */
class PosCart extends Model
{
    use HasFactory;

    public function appliedDiscounts(): HasMany
    {
        return $this->hasMany(PosAppliedDiscount::class, 'cart_id');
    }

    protected $fillable = [
        'store_id',
        'session_id',
        'user_id',
        'customer_id',
        'status',
        'subtotal',
        'subtotal_amount',
        'discount_total',
        'tax_total',
        'grand_total',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosCartItem::class, 'cart_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
