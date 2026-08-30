<?php

// app/Http/Requests/Product/UpdateBrandStatusRequest.php

namespace App\Http\Requests\Products\Brands;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['bail', 'required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status wajib diisi.',
            'status.in' => 'Status harus salah satu dari: active, inactive.',
        ];
    }
}
