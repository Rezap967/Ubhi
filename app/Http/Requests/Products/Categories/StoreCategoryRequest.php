<?php

// app/Http/Requests/Product/StoreCategoryRequest.php

namespace App\Http\Requests\Products\Categories;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
                'unique:product_categories,name,NULL,id,deleted_at,NULL',
            ],
            'description' => ['bail', 'nullable', 'string'],
            'status' => ['bail', 'nullable', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.string' => 'Nama kategori harus berupa teks.',
            'name.max' => 'Nama kategori maksimal 100 karakter.',
            'name.unique' => 'Nama kategori sudah digunakan.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'status.in' => 'Status harus salah satu dari: active, inactive.',
        ];
    }
}
