<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class CurrentPosSessionRequest extends FormRequest
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
            'device_id' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_id.required' => 'Device ID wajib diisi.',
            'device_id.string' => 'Device ID harus berupa teks.',
            'device_id.max' => 'Device ID maksimal 100 karakter.',
        ];
    }
}
