<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class ReferenceNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'in:journal'],
            'date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipe nomor referensi tidak valid.',
            'date.date' => 'Tanggal referensi tidak valid.',
        ];
    }
}
