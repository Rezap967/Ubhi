<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'code', 'provider', 'account_name', 'account_number', 'instructions'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $this->merge([
                    $field => is_string($value) ? trim($value) : $value,
                ]);
            }
        }

        $name = trim((string) ($this->input('name') ?? ''));
        $code = trim((string) ($this->input('code') ?? ''));

        if ($this->has('name') && $code === '') {
            $this->merge(['code' => strtolower(Str::slug($name, '_'))]);
        }

        if ($this->has('code') && $code !== '') {
            $this->merge(['code' => strtolower($code)]);
        }
    }

    public function rules(): array
    {
        $methodId = $this->route('paymentMethod')?->id ?? null;
        $storeId = $this->user()?->getStoreId() ?? 0;

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^[a-z0-9_\-]+$/',
                'unique:payment_methods,code,'.($methodId ?? 'NULL').',id,store_id,'.$storeId,
            ],
            'type' => ['sometimes', 'in:qris,debit_card,credit_card,bank_transfer,e_wallet,cash'],
            'provider' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'qr_image' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode metode pembayaran sudah digunakan di toko ini.',
            'code.regex' => 'Kode hanya boleh berisi huruf kecil, angka, underscore, dan strip.',
            'type.in' => 'Tipe metode pembayaran tidak valid.',
            'is_active.boolean' => 'Status aktif harus bernilai benar atau salah.',
        ];
    }
}
