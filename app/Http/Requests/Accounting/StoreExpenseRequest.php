<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');
        $referenceNo = $this->input('reference_no');

        $this->merge([
            'description' => is_string($description) ? trim($description) : $description,
            'reference_no' => is_string($referenceNo) ? (trim($referenceNo) ?: null) : $referenceNo,
            'tax_amount' => $this->input('tax_amount', 0),
            'status' => $this->input('status', 'draft'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $storeId = active_store_id();

        return [
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => [
                'nullable', 'uuid',
                Rule::exists('expense_categories', 'id')->where(fn ($query) => $query
                    ->where('store_id', $storeId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'expense_account_id' => [
                'required', 'uuid',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('store_id', $storeId)
                    ->where('is_active', true)
                    ->where('type', 'expense')
                    ->whereNull('deleted_at')),
            ],
            'cash_or_bank_account_id' => [
                'required', 'uuid',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('store_id', $storeId)
                    ->where('is_active', true)
                    ->where('type', 'asset')
                    ->whereNull('deleted_at')),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax_rate_id' => ['nullable', 'uuid'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'transfer', 'ewallet', 'other'])],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'posted'])],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_date.required' => 'Tanggal pengeluaran wajib diisi.',
            'expense_date.date' => 'Tanggal pengeluaran tidak valid.',
            'description.required' => 'Deskripsi pengeluaran wajib diisi.',
            'description.max' => 'Deskripsi pengeluaran maksimal 255 karakter.',
            'category_id.exists' => 'Kategori tidak valid atau bukan milik toko aktif.',
            'expense_account_id.required' => 'Akun beban wajib dipilih.',
            'expense_account_id.exists' => 'Akun beban tidak valid, tidak aktif, atau bukan milik toko aktif.',
            'cash_or_bank_account_id.required' => 'Akun kas/bank wajib dipilih.',
            'cash_or_bank_account_id.exists' => 'Akun kas/bank tidak valid, tidak aktif, atau bukan milik toko aktif.',
            'amount.required' => 'Jumlah pengeluaran wajib diisi.',
            'amount.numeric' => 'Jumlah pengeluaran harus berupa angka.',
            'amount.gt' => 'Jumlah pengeluaran harus lebih besar dari nol.',
            'tax_amount.numeric' => 'Nilai pajak harus berupa angka.',
            'tax_amount.min' => 'Nilai pajak tidak boleh kurang dari nol.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
            'status.in' => 'Status pengeluaran tidak valid.',
        ];
    }
}
