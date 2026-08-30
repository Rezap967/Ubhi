<?php

namespace App\Http\Requests\Kasir\Discounts;

use Illuminate\Foundation\Http\FormRequest;

class RemoveDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via store scope
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
        ];
    }
}
