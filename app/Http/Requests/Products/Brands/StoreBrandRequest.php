<?php

// app/Http/Requests/Product/StoreBrandRequest.php

namespace App\Http\Requests\Products\Brands;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'bail', 'required', 'string', 'max:100',
                'unique:brands,name,NULL,id,deleted_at,NULL',
            ],
            'description' => ['bail', 'nullable', 'string'],
            'status' => ['bail', 'nullable', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama merek wajib diisi.',
            'name.string' => 'Nama merek harus berupa teks.',
            'name.max' => 'Nama merek maksimal 100 karakter.',
            'name.unique' => 'Nama merek sudah digunakan.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'status.in' => 'Status harus salah satu dari: active, inactive.',
        ];
    }
}
