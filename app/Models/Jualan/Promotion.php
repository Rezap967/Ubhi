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

class Promotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'type',
        'discount_value',
        'max_discount',
        'min_purchase',
        'min_qty',
        'start_date',
        'end_date',
        'scope',
        'max_usage',
        'per_customer_limit',
        'usage_count',
        'is_stackable',
        'priority',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'min_qty' => 'integer',
        'max_usage' => 'integer',
        'per_customer_limit' => 'integer',
        'usage_count' => 'integer',
        'is_stackable' => 'boolean',
        'priority' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'deleted_at' => 'datetime',
    ];

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

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

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

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['diarsipkan']);
    }
}
