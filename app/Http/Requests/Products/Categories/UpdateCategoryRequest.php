<?php

// app/Http/Requests/Product/UpdateCategoryRequest.php

namespace App\Http\Requests\Products\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id;

        return [
            'name' => [
                'bail', 'required', 'string', 'max:100',
                Rule::unique('product_categories', 'name')
                    ->ignore($categoryId)
                    ->whereNull('deleted_at'),
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
