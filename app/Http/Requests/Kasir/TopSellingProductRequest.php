<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class TopSellingProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'in:today,week,month,custom'],
            'start_date' => ['required_if:period,custom', 'date'],
            'end_date' => ['required_if:period,custom', 'date', 'after_or_equal:start_date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
