<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPayablePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'cash_or_bank_account_id' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where('store_id', active_store_id())->where('is_active', true)->where('type', 'asset'),
            ],
            'payment_method' => ['required', 'in:cash,bank,transfer,ewallet,other'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'payment_date.date' => 'Format tanggal pembayaran tidak valid.',
            'amount.required' => 'Jumlah pembayaran wajib diisi.',
            'amount.numeric' => 'Jumlah pembayaran harus berupa angka.',
            'amount.min' => 'Jumlah pembayaran harus lebih dari 0.',
            'cash_or_bank_account_id.required' => 'Akun kas/bank wajib dipilih.',
            'cash_or_bank_account_id.exists' => 'Akun kas/bank tidak valid.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
        ];
    }
}
