<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptRequest extends FormRequest
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
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'payment_status' => ['required', 'in:lunas,belum_lunas'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'received_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib dipilih.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.gt' => 'Jumlah harus lebih dari 0.',
            'unit_id.required' => 'Satuan wajib dipilih.',
            'warehouse_id.required' => 'Gudang tujuan wajib dipilih.',
            'payment_status.required' => 'Status pembayaran wajib dipilih.',
            'received_date.required' => 'Tanggal penerimaan wajib diisi.',
        ];
    }
}
