<?php

namespace App\Http\Requests\Konsinyasi;

use Illuminate\Foundation\Http\FormRequest;

class FilterConsignmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->store_id !== null;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive,closed'],
            'sort_by' => ['nullable', 'in:created_at,selling_price,consignment_price,status'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}