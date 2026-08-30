<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'expense_category_id' => $this->expense_category_id,
            'category_name' => $this->category?->name,
            'amount' => (float) $this->amount,
            'frequency' => $this->frequency,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'status' => $this->status,
            'next_due_date' => $this->next_due_date ? $this->next_due_date->format('Y-m-d') : null,
            'last_generated_at' => $this->last_generated_at ? $this->last_generated_at->toDateTimeString() : null,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
