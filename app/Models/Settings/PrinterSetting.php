<?php

namespace App\Models\Settings;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'name', 'type', 'paper_size', 'copies', 'is_default', 'status',
    ];

    protected $casts = [
        'copies' => 'integer',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
