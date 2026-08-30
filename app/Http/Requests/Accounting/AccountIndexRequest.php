<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Accounting\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $value = $this->input('is_active');

            $this->merge([
                'is_active' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(AccountType::values())],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Pencarian akun harus berupa teks.',
            'search.max' => 'Pencarian akun maksimal 100 karakter.',
            'type.in' => 'Tipe akun tidak valid.',
            'is_active.boolean' => 'Filter status aktif harus berupa boolean.',
            'per_page.integer' => 'Jumlah data per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah data per halaman minimal 1.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
