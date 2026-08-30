<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'recurring_code',
        'name',
        'expense_category_id',
        'amount',
        'frequency',
        'start_date',
        'end_date',
        'payment_method',
        'notes',
        'status',
        'next_due_date',
        'last_generated_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'next_due_date' => 'date:Y-m-d',
        'last_generated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(RecurringExpenseGeneration::class);
    }
}
