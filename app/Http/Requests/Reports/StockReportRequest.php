<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class StockReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'low_stock_only' => ['nullable', 'boolean'],
            'dead_stock_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'sort_by' => ['nullable', 'in:product_name,quantity,stock_value,last_movement_at'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
        ];
    }
}
