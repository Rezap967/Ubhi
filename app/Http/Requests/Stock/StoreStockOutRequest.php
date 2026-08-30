<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'reason' => ['required', Rule::in(['rusak', 'expired', 'pemakaian_internal', 'hibah', 'lainnya'])],
            'out_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'required_if:reason,lainnya', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib dipilih.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.gt' => 'Jumlah harus lebih dari 0.',
            'unit_id.required' => 'Satuan wajib dipilih.',
            'warehouse_id.required' => 'Gudang asal wajib dipilih.',
            'reason.required' => 'Alasan pengeluaran wajib dipilih.',
            'reason.in' => 'Alasan pengeluaran tidak valid.',
            'out_date.required' => 'Tanggal keluar wajib diisi.',
        ];
    }
}
