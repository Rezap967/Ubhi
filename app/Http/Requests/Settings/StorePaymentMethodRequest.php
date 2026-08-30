<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) ($this->input('name') ?? ''));
        $code = trim((string) ($this->input('code') ?? ''));

        $this->merge([
            'name' => $name,
            'code' => $code !== '' ? strtolower($code) : strtolower(Str::slug($name, '_')),
            'provider' => $this->input('provider') !== null ? trim((string) $this->input('provider')) : null,
            'account_name' => $this->input('account_name') !== null ? trim((string) $this->input('account_name')) : null,
            'account_number' => $this->input('account_number') !== null ? trim((string) $this->input('account_number')) : null,
            'instructions' => $this->input('instructions') !== null ? trim((string) $this->input('instructions')) : null,
            'is_active' => $this->has('is_active') ? (bool) $this->input('is_active') : true,
            'sort_order' => $this->input('sort_order') ?? 0,
        ]);
    }

    public function rules(): array
    {
        $storeId = $this->user()?->getStoreId() ?? 0;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_\-]+$/',
                'unique:payment_methods,code,NULL,id,store_id,'.$storeId,
            ],
            'type' => ['required', 'in:qris,debit_card,credit_card,bank_transfer,e_wallet,cash'],
            'provider' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'qr_image' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama metode pembayaran wajib diisi.',
            'name.max' => 'Nama metode pembayaran maksimal 100 karakter.',
            'code.required' => 'Kode metode pembayaran wajib diisi.',
            'code.regex' => 'Kode hanya boleh berisi huruf kecil, angka, underscore, dan strip.',
            'code.unique' => 'Kode metode pembayaran sudah digunakan di toko ini.',
            'type.required' => 'Tipe metode pembayaran wajib dipilih.',
            'type.in' => 'Tipe metode pembayaran tidak valid.',
            'is_active.boolean' => 'Status aktif harus bernilai benar atau salah.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
            'sort_order.min' => 'Urutan minimal 0.',
        ];
    }
}
