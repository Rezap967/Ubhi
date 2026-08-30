<?php

// app/Http/Requests/Products/Warehouses/UpdateWarehouseRequest.php

namespace App\Http\Requests\Products\Warehouses;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'code' => is_string($this->code) && $this->code !== ''
                ? strtoupper(trim($this->code))
                : $this->code,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'code' => ['bail', 'nullable', 'string', 'max:50'],
            'address' => ['bail', 'nullable', 'string'],
            'phone' => ['bail', 'nullable', 'string', 'max:50'],
            'description' => ['bail', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama gudang wajib diisi.',
            'name.max' => 'Nama gudang maksimal 255 karakter.',
            'code.max' => 'Kode gudang maksimal 50 karakter.',
            'phone.max' => 'Nomor telepon maksimal 50 karakter.',
        ];
    }
}
