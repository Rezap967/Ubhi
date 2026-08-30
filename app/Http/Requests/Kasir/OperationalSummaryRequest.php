<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class OperationalSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_id.required' => 'Device ID wajib diisi.',
            'device_id.max' => 'Device ID maksimal 100 karakter.',
        ];
    }
}
