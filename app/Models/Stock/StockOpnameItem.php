<?php

namespace App\Models\Stock;

use App\Models\Products\Product;
use App\Models\Products\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'product_id',
        'warehouse_id',
        'system_stock',
        'physical_stock',
        'difference',
        'updated_by',
    ];

    protected $casts = [
        'system_stock' => 'decimal:2',
        'physical_stock' => 'decimal:2',
        'difference' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockOpnameSession::class, 'session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
