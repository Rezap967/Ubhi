<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class AllSessionsSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'store_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date' => 'Format tanggal tidak valid.',
            'store_id.integer' => 'Store ID harus berupa angka.',
        ];
    }
}
