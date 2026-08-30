<?php

// app/Http/Requests/Product/StoreUnitRequest.php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi ditangani via $this->authorize() di controller (Policy)
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
            'name' => ['required', 'string', 'max:100', 'unique:product_units,name,NULL,id,deleted_at,NULL'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama satuan wajib diisi.',
            'name.string' => 'Nama satuan harus berupa teks.',
            'name.max' => 'Nama satuan maksimal 100 karakter.',
            'name.unique' => 'Nama satuan sudah digunakan.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'status.in' => 'Status harus salah satu dari: active, inactive.',
        ];
    }
}
