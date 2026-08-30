<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class CheckStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'unit_id' => ['required', 'integer', 'exists:product_units,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'warehouse_id.exists' => 'Gudang tidak ditemukan.',
            'unit_id.required' => 'Satuan wajib dipilih.',
            'unit_id.exists' => 'Satuan tidak ditemukan.',
        ];
    }
}
