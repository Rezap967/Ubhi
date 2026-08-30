<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class ClosePosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Akses dikontrol oleh middleware role:kasir,owner dan store.scope
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closing_cash.required' => 'Modal akhir (closing cash) wajib diisi.',
            'closing_cash.numeric' => 'Modal akhir harus berupa angka.',
            'closing_cash.min' => 'Modal akhir minimal 0.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
