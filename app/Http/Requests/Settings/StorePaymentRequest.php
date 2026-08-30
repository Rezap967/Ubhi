<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah ditangani middleware 'role:owner'
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', 'string', 'max:100'],
            'payment_type' => ['required', 'in:qris,transfer,ewallet,cash'],
            'merchant_id' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama metode pembayaran wajib diisi.',
            'provider.required' => 'Provider wajib diisi.',
            'payment_type.required' => 'Jenis pembayaran wajib dipilih.',
            'payment_type.in' => 'Jenis pembayaran harus salah satu dari: qris, transfer, ewallet, cash.',
            'status.in' => 'Status harus salah satu dari: active, inactive.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
            'sort_order.max' => 'Urutan maksimal 255.',
        ];
    }
}
