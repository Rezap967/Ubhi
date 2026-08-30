<?php

namespace App\Http\Requests\Products\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:aktif,nonaktif',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status wajib diisi.',
            'status.in' => 'Status harus aktif atau nonaktif.',
        ];
    }
}
