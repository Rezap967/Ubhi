<?php

namespace App\Models\Kasir;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property int $user_id
 * @property string $device_id
 * @property string $status
 * @property float $opening_cash
 * @property ?float $closing_cash
 * @property float $expected_cash
 * @property ?float $cash_difference
 * @property ?Carbon $opened_at
 * @property ?Carbon $closed_at
 */
class PosSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
        'device_id',
        'status',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_difference',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(PosCart::class, 'session_id');
    }

    public function activeCart(): HasOne
    {
        return $this->hasOne(PosCart::class, 'session_id')->where('status', 'active');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForDevice(Builder $query, string $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }
}
