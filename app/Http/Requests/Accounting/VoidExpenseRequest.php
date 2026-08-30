<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class VoidExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'void_reason.required' => 'Alasan pembatalan wajib diisi.',
            'void_reason.max' => 'Alasan pembatalan maksimal 1000 karakter.',
        ];
    }
}
