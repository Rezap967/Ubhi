<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerReceivablePaymentRequest extends FormRequest
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
            'cash_or_bank_account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')->where('store_id', active_store_id())->where('is_active', true)->where('type', 'asset')],
            'payment_method' => ['required', 'in:cash,bank,transfer,ewallet,other'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_date.required' => 'Tanggal penerimaan wajib diisi.',
            'payment_date.date' => 'Format tanggal penerimaan tidak valid.',
            'amount.required' => 'Jumlah penerimaan wajib diisi.',
            'amount.numeric' => 'Jumlah penerimaan harus berupa angka.',
            'amount.min' => 'Jumlah penerimaan harus lebih dari 0.',
            'cash_or_bank_account_id.required' => 'Akun kas/bank wajib dipilih.',
            'cash_or_bank_account_id.exists' => 'Akun kas/bank harus aktif, bertipe asset, dan milik toko aktif.',
            'payment_method.required' => 'Metode penerimaan wajib dipilih.',
            'payment_method.in' => 'Metode penerimaan tidak valid.',
        ];
    }
}
