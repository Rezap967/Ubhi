<?php

// app/Models/Products/Warehouse.php

namespace App\Models\Products;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'address',
        'phone',
        'description',
        'warehouse_type',
        'created_by',
        'updated_by',
    ];

    /**
     * Mapping warehouse_type ke label (hardcoded — tidak ada lookup table).
     */
    public static array $typeLabels = [
        'store_stock' => 'Stok di Toko',
        'warehouse' => 'Gudang',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::$typeLabels[$this->warehouse_type] ?? $this->warehouse_type;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->code
            ? "{$this->name} ({$this->code})"
            : $this->name;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
