<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('store_id', active_store_id())],
            'receivable_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:receivable_date'],
            'description' => ['nullable', 'string', 'max:255'],
            'receivable_account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')->where('store_id', active_store_id())->where('is_active', true)->where('type', 'asset')],
            'revenue_account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')->where('store_id', active_store_id())->where('is_active', true)->where('type', 'revenue')],
            'original_amount' => ['required', 'numeric', 'min:1'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
            'customer_name.max' => 'Nama pelanggan tidak boleh lebih dari 150 karakter.',
            'customer_id.exists' => 'Pelanggan tidak valid atau bukan milik toko aktif.',
            'receivable_date.required' => 'Tanggal piutang wajib diisi.',
            'receivable_date.date' => 'Format tanggal piutang tidak valid.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_date.date' => 'Format tanggal jatuh tempo tidak valid.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo harus sama atau setelah tanggal piutang.',
            'receivable_account_id.required' => 'Akun piutang wajib dipilih.',
            'receivable_account_id.exists' => 'Akun piutang harus aktif, bertipe asset, dan milik toko aktif.',
            'revenue_account_id.required' => 'Akun pendapatan wajib dipilih.',
            'revenue_account_id.exists' => 'Akun pendapatan harus aktif, bertipe revenue, dan milik toko aktif.',
            'original_amount.required' => 'Jumlah piutang wajib diisi.',
            'original_amount.numeric' => 'Jumlah piutang harus berupa angka.',
            'original_amount.min' => 'Jumlah piutang harus lebih dari 0.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['customer_name' => trim((string) $this->input('customer_name', ''))]);
    }
}
