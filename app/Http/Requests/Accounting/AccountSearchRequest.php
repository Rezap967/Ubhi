<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Accounting\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(AccountType::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'Pencarian akun harus berupa teks.',
            'q.max' => 'Pencarian akun maksimal 100 karakter.',
            'search.string' => 'Pencarian akun harus berupa teks.',
            'search.max' => 'Pencarian akun maksimal 100 karakter.',
            'type.in' => 'Tipe akun tidak valid.',
        ];
    }
}
