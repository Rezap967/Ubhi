<?php

namespace App\Models\Jualan;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'delivery_number',
        'source_type',
        'order_id',
        'customer_order_id',
        'recipient_name',
        'recipient_address',
        'delivery_date',
        'status',
        'printed_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'printed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'customer_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}