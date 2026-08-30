<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DashboardSalesChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'in:today,week,month,year'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'compare' => ['nullable', 'boolean'],
        ];
    }
}
