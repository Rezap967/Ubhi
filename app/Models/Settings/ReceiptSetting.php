<?php

namespace App\Models\Settings;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'header_text', 'footer_text', 'show_logo', 'show_qr_code',
        'show_tax', 'show_discount', 'show_cashier_name', 'tax_label', 'tax_percent',
    ];

    protected $casts = [
        'show_logo' => 'boolean',
        'show_qr_code' => 'boolean',
        'show_tax' => 'boolean',
        'show_discount' => 'boolean',
        'show_cashier_name' => 'boolean',
        'tax_percent' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
