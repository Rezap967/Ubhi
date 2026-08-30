<?php

namespace App\Models\Stock;

use App\Models\Products\Category;
use App\Models\Products\Warehouse;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpnameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'session_code',
        'session_name',
        'scope_type',
        'warehouse_id',
        'category_id',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class, 'session_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Label scope untuk tampilan frontend.
     */
    public function getScopeLabelAttribute(): string
    {
        return match ($this->scope_type) {
            'all_products' => 'Semua Produk',
            'per_warehouse' => 'Per Gudang',
            'per_category' => 'Per Kategori',
            default => $this->scope_type,
        };
    }

    /**
     * Apakah sesi masih aktif (belum disetujui).
     */
    public function isRunning(): bool
    {
        return $this->status === 'berjalan';
    }

    /**
     * Apakah sesi sudah final.
     */
    public function isApproved(): bool
    {
        return $this->status === 'disetujui';
    }
}
