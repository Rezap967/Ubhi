<?php

namespace App\Models\Kasir;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosReceipt extends Model
{
    protected $fillable = [
        'transaction_id',
        'receipt_number',
        'receipt_payload',
        'print_count',
        'printed_at',
    ];

    protected $casts = [
        'receipt_payload' => 'array',
        'printed_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class, 'transaction_id');
    }
}
