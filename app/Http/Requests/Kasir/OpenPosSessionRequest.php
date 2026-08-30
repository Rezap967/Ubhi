<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class OpenPosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Akses dikontrol oleh middleware role:kasir,owner dan store.scope
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'device_id' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_cash.required' => 'Modal awal (opening cash) wajib diisi.',
            'opening_cash.numeric' => 'Modal awal harus berupa angka.',
            'opening_cash.min' => 'Modal awal minimal 0.',
            'device_id.required' => 'Device ID wajib diisi.',
            'device_id.string' => 'Device ID harus berupa teks.',
            'device_id.max' => 'Device ID maksimal 100 karakter.',
        ];
    }
}
