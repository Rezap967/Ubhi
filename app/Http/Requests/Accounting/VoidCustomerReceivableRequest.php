<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class VoidCustomerReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['void_reason' => ['required', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'void_reason.required' => 'Alasan pembatalan wajib diisi.',
            'void_reason.max' => 'Alasan pembatalan tidak boleh lebih dari 1000 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['void_reason' => trim((string) $this->input('void_reason', ''))]);
    }
}
