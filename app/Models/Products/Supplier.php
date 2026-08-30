<?php

namespace App\Models\Products;

use App\Models\User;
use App\Models\Konsinyasi\ConsignmentItem;
use App\Models\Konsinyasi\ConsignmentSalesAllocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// [ACTIVATE WHEN PRODUCT READY]
// use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'address',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope: hanya pemasok berstatus 'aktif'.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function consignmentItems(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class, 'supplier_id');
    }

    public function consignmentSalesAllocations(): HasMany
    {
        return $this->hasMany(ConsignmentSalesAllocation::class, 'supplier_id');
    }
    // [ACTIVATE WHEN PRODUCT READY]
    // public function products(): BelongsToMany
    // {
    //     return $this->belongsToMany(Product::class, 'supplier_products')
    //                 ->whereNull('products.deleted_at');
    // }
}
