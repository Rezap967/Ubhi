<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class ReverseJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return [
            'reason.string' => 'Alasan reversal harus berupa teks.',
            'reason.max' => 'Alasan reversal maksimal 500 karakter.',
        ];
    }
}
