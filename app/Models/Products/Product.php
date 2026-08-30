<?php

// app/Models/Products/Product.php

namespace App\Models\Products;

use App\Models\Stock\StockBalance;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'category_id',
        'brand_id',
        'unit_id',
        'parent_id',
        'name',
        'description',
        'sku',
        'barcode',
        'type',
        'sell_price',
        'buy_price',
        'initial_stock',
        'minimum_stock',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'buy_price' => 'decimal:2',
        'initial_stock' => 'integer',
    ];

    /**
     * Margin dihitung secara runtime, tidak disimpan di DB.
     */
    public function getMarginAttribute(): float
    {
        if ((float) $this->sell_price > 0) {
            return round((($this->sell_price - $this->buy_price) / $this->sell_price) * 100, 2);
        }

        return 0.0;
    }

    /**
     * Label tipe produk sesuai SAD § 3.1.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'variant' => 'variant',
            'bundle' => 'bundle',
            default => 'Barang',
        };
    }

    /**
     * Label status produk (bahasa Indonesia).
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'aktif' ? 'Aktif' : 'Nonaktif';
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Hanya produk induk (bukan varian anak).
     */
    public function scopeParentOnly(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Filter berdasarkan status.
     *
     * @param  'aktif'|'nonaktif'|'semua'  $status
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        if ($status === 'semua') {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Pencarian keyword (nama, SKU, barcode).
     */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if (! $keyword || trim($keyword) === '') {
            return $query;
        }

        $like = '%'.mb_strtolower(trim($keyword)).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                ->orWhereRaw('LOWER(barcode) LIKE ?', [$like]);
        });
    }

    /**
     * Filter berdasarkan kategori.
     */
    public function scopeWithCategory(Builder $query, int|string|null $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope aktif saja.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    /**
     * Produk induk (hanya berlaku untuk varian anak).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    /**
     * Varian anak dari produk induk ini.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id')->whereNull('deleted_at');
    }

    /**
     * Gambar produk.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }

    /**
     * Harga spesial produk.
     */
    public function specialPrices(): HasMany
    {
        return $this->hasMany(ProductSpecialPrice::class, 'product_id');
    }

    /**
     * Pemasok produk (many-to-many melalui supplier_products).
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products', 'product_id', 'supplier_id')
            ->withPivot('created_at');
    }

    /**
     * Saldo stok berjalan produk per gudang / store.
     */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'product_id');
    }
}
