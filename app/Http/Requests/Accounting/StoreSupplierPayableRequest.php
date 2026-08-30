<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_name' => ['required', 'string', 'max:150'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'payable_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:payable_date'],
            'description' => ['nullable', 'string', 'max:255'],
            'expense_or_inventory_account_id' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where('store_id', active_store_id())->where('is_active', true)->whereIn('type', ['expense', 'asset']),
            ],
            'liability_account_id' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where('store_id', active_store_id())->where('is_active', true)->where('type', 'liability'),
            ],
            'original_amount' => ['required', 'numeric', 'min:1'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'supplier_name.required' => 'Nama pemasok wajib diisi.',
            'supplier_name.max' => 'Nama pemasok tidak boleh lebih dari 150 karakter.',
            'payable_date.required' => 'Tanggal hutang wajib diisi.',
            'payable_date.date' => 'Format tanggal hutang tidak valid.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_date.date' => 'Format tanggal jatuh tempo tidak valid.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo harus sama atau setelah tanggal hutang.',
            'expense_or_inventory_account_id.required' => 'Akun beban/persediaan wajib dipilih.',
            'expense_or_inventory_account_id.exists' => 'Akun beban/persediaan tidak valid.',
            'liability_account_id.required' => 'Akun kewajiban wajib dipilih.',
            'liability_account_id.exists' => 'Akun kewajiban tidak valid.',
            'original_amount.required' => 'Jumlah hutang wajib diisi.',
            'original_amount.numeric' => 'Jumlah hutang harus berupa angka.',
            'original_amount.min' => 'Jumlah hutang harus lebih dari 0.',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'supplier_name' => trim($this->supplier_name ?? ''),
        ]);
    }
}
