<?php

namespace App\Models\Settings;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'provider',
        'merchant_id',
        'account_number',
        'payment_type',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'payment_type' => 'string',
        'status' => 'string',
        'sort_order' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }
}
