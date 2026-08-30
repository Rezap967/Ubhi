<?php

namespace App\Models\Kasir;

use App\Models\Customer\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $transaction_number
 * @property string $idempotency_key
 * @property int $store_id
 * @property int $session_id
 * @property int $cart_id
 * @property int $user_id
 * @property ?int $customer_id
 * @property string $status
 * @property float $subtotal
 * @property float $discount_total
 * @property float $tax_total
 * @property float $grand_total
 * @property float $paid_amount
 * @property float $change_amount
 * @property string $source
 * @property ?string $notes
 * @property Carbon $transaction_at
 */
class PosTransaction extends Model
{
    protected $fillable = [
        'transaction_number',
        'idempotency_key',
        'store_id',
        'session_id',
        'cart_id',
        'user_id',
        'customer_id',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'paid_amount',
        'change_amount',
        'source',
        'notes',
        'transaction_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'transaction_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(PosCart::class, 'cart_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosTransactionItem::class, 'transaction_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'transaction_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PosReceipt::class, 'transaction_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
