<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class JournalEntrySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Pencarian jurnal harus berupa teks.',
            'search.max' => 'Pencarian jurnal maksimal 100 karakter.',
            'limit.integer' => 'Limit harus berupa angka.',
            'limit.min' => 'Limit minimal 1.',
            'limit.max' => 'Limit maksimal 20.',
        ];
    }
}
