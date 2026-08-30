<?php

namespace App\Models\Jualan;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'code',
        'name',
        'discount_type',
        'discount_value',
        'max_discount',
        'min_purchase',
        'description',
        'start_date',
        'end_date',
        'total_quota',
        'used_count',
        'per_customer_limit',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'total_quota' => 'integer',
        'used_count' => 'integer',
        'per_customer_limit' => 'integer',
        'deleted_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(CouponCode::class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────────

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Check if coupon is eligible for use.
     * Status must be aktif AND within valid date range.
     */
    public function isEligible(): bool
    {
        if ($this->status !== 'aktif') {
            return false;
        }

        $today = now()->toDateString();

        return $this->start_date <= $today && $this->end_date >= $today;
    }

    /**
     * Check if coupon quota is exhausted.
     */
    public function isQuotaExhausted(): bool
    {
        if ($this->total_quota === null) {
            return false; // Unlimited
        }

        return $this->used_count >= $this->total_quota;
    }

    /**
     * Calculate discount amount for given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        $discount = 0.0;

        if ($this->discount_type === 'persentase') {
            $discount = $subtotal * $this->discount_value / 100;

            // Apply max_discount cap if set
            if ($this->max_discount !== null) {
                $discount = min($discount, (float) $this->max_discount);
            }
        } else {
            // nominal
            $discount = (float) $this->discount_value;
        }

        // Clamp discount to not exceed subtotal
        return min($discount, $subtotal);
    }
}
