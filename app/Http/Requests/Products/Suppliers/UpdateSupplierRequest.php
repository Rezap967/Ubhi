<?php

namespace App\Http\Requests\Products\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sebenarnya dilakukan via $this->authorize('update', $supplier)
        // di controller, sehingga di sini cukup true.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'code' => is_string($this->code) ? trim($this->code) : $this->code,
        ]);
    }

    public function rules(): array
    {
        return [
            // unique exclude self
            'code' => 'nullable|string|max:50|unique:suppliers,code,'.$this->route('supplier')?->id,
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:aktif,nonaktif',
            // Perhatian: enum menggunakan 'aktif'/'nonaktif' (Indonesia)
        ];
    }

    public function messages(): array
    {
        return [
            'code.max' => 'Kode pemasok maksimal 50 karakter.',
            'code.unique' => 'Kode pemasok sudah digunakan.',
            'name.required' => 'Nama pemasok wajib diisi.',
            'name.max' => 'Nama pemasok maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'phone.max' => 'Nomor telepon maksimal 50 karakter.',
            'status.in' => 'Status harus aktif atau nonaktif.',
        ];
    }
}
