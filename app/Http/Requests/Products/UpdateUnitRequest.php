<?php

// app/Http/Requests/Product/UpdateUnitRequest.php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
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
        $unitId = $this->route('unit')?->id ?? $this->route('unit');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_units', 'name')
                    ->ignore($unitId)
                    ->whereNull('deleted_at'),
            ],
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
