<?php

namespace App\Http\Requests\Accounting\RecurringExpenses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $storeId = $user->profile?->store_id;

        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'expense_category_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('expense_categories', 'id')->where(function ($query) use ($storeId) {
                    $query->whereNull('store_id')->orWhere('store_id', $storeId);
                }),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'debit', 'kredit', 'ewallet'])],
            'notes' => ['nullable', 'string', 'max:500'],

            // Blacklist parameter terlarang dari client
            'status' => ['prohibited'],
            'store_id' => ['prohibited'],
            'expense_account_id' => ['prohibited'],
            'cash_or_bank_account_id' => ['prohibited'],
            'next_due_date' => ['prohibited'],
            'recurring_code' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama biaya wajib diisi.',
            'name.min' => 'Nama biaya minimal 3 karakter.',
            'name.max' => 'Nama biaya maksimal 150 karakter.',
            'expense_category_id.required' => 'Kategori biaya wajib dipilih.',
            'expense_category_id.exists' => 'Kategori biaya tidak valid.',
            'amount.required' => 'Nominal biaya wajib diisi.',
            'amount.gt' => 'Nominal biaya harus lebih dari 0.',
            'frequency.required' => 'Frekuensi pembayaran wajib dipilih.',
            'frequency.in' => 'Frekuensi pembayaran tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date' => 'Format tanggal mulai tidak valid.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
            'status.prohibited' => 'Status tidak boleh dikirim saat menyimpan data.',
        ];
    }
}
