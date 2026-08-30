<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Accounting\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->code) ? trim($this->code) : $this->code,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => [
                'bail',
                'required',
                'string',
                'max:50',
                Rule::unique('accounts', 'code')->where(
                    fn ($query) => $query->where('store_id', active_store_id()),
                ),
            ],
            'name' => ['bail', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['bail', 'required', 'string', Rule::in(AccountType::values())],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounts', 'id')->where(
                    fn ($query) => $query
                        ->where('store_id', active_store_id())
                        ->where('is_active', true),
                ),
            ],
            'sort_order' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode akun wajib diisi.',
            'code.string' => 'Kode akun harus berupa teks.',
            'code.max' => 'Kode akun maksimal 50 karakter.',
            'code.unique' => 'Kode akun sudah digunakan di toko ini.',
            'name.required' => 'Nama akun wajib diisi.',
            'name.string' => 'Nama akun harus berupa teks.',
            'name.max' => 'Nama akun maksimal 150 karakter.',
            'description.string' => 'Deskripsi akun harus berupa teks.',
            'description.max' => 'Deskripsi akun maksimal 1000 karakter.',
            'type.required' => 'Tipe akun wajib dipilih.',
            'type.in' => 'Tipe akun tidak valid.',
            'parent_id.uuid' => 'Parent akun tidak valid.',
            'parent_id.exists' => 'Parent akun tidak ditemukan atau tidak aktif.',
            'sort_order.integer' => 'Urutan akun harus berupa angka bulat.',
        ];
    }
}
