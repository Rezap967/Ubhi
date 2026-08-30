<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'provider' => ['sometimes', 'string', 'max:100'],
            'payment_type' => ['sometimes', 'in:qris,transfer,ewallet,cash'],
            'merchant_id' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_type.in' => 'Jenis pembayaran harus salah satu dari: qris, transfer, ewallet, cash.',
            'status.in' => 'Status harus salah satu dari: active, inactive.',
            'sort_order.max' => 'Urutan maksimal 255.',
        ];
    }
}
