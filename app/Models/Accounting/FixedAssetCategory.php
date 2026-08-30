<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int $store_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $asset_account_id
 * @property string|null $accumulated_depreciation_account_id
 * @property string|null $depreciation_expense_account_id
 * @property int|null $default_useful_life_months
 * @property string $depreciation_method
 * @property string|null $depreciation_rate
 * @property string $residual_value_type
 * @property string|null $residual_value
 * @property bool $is_active
 * @property bool $is_system
 * @property int $sort_order
 */
class FixedAssetCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'fixed_asset_categories';

    protected $fillable = [
        'store_id', 'code', 'name', 'description', 'asset_account_id',
        'accumulated_depreciation_account_id', 'depreciation_expense_account_id',
        'default_useful_life_months', 'depreciation_method', 'depreciation_rate',
        'residual_value_type', 'residual_value', 'is_active', 'is_system',
        'sort_order', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'default_useful_life_months' => 'integer',
        'depreciation_rate' => 'decimal:4',
        'residual_value' => 'decimal:2',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Account, $this> */
    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param Builder<self> $query */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }
}
