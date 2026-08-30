<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class BalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'as_of_date' => ['nullable', 'string'],
            'date_end' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'as_of_date.string' => 'Tanggal laporan tidak valid.',
            'date_end.string' => 'Tanggal laporan tidak valid.',
        ];
    }

    public function prepareForValidation(): void
    {
        // No-op: normalization happens in service
    }
}
