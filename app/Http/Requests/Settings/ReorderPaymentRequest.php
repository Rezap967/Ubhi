<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.id' => ['required', 'integer', 'exists:payment_settings,id'],
            'payments.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'payments.required' => 'Data urutan wajib diisi.',
            'payments.array' => 'Format data urutan tidak valid.',
            'payments.*.id.exists' => 'Salah satu metode pembayaran tidak ditemukan.',
            'payments.*.sort_order.required' => 'Urutan wajib diisi untuk setiap metode pembayaran.',
        ];
    }
}
