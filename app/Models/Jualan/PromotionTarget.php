<?php

namespace App\Models\Jualan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionTarget extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'target_id' => 'integer',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
