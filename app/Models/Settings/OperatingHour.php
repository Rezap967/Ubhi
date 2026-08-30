<?php

namespace App\Models\Settings;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'day_of_week', 'day_name', 'open_time', 'close_time', 'is_open',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_open' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
