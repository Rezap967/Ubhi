<?php

namespace App\Http\Requests\Konsinyasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsignmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->store_id !== null;
    }

    public function rules(): array
    {
        $storeId = $this->user()->store_id;

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('store_id', $storeId),
            ],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('store_id', $storeId),
            ],
            'default_warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where('store_id', $storeId),
            ],
            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where('store_id', $storeId),
            ],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'consignment_price' => ['required', 'numeric', 'min:0', 'lte:selling_price'],
            'commission_type' => ['required', 'in:margin,percentage,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'initial_quantity' => ['nullable', 'numeric', 'min:0'],
            'received_date' => ['required_if:initial_quantity,>0', 'nullable', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Validasi tambahan yang tidak bisa diekspresikan lewat rule array biasa:
     * kombinasi (store_id, product_id, supplier_id) harus unik.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $storeId = $this->user()->store_id;
            $productId = $this->input('product_id');
            $supplierId = $this->input('supplier_id');

            if (!$productId || !$supplierId) {
                return;
            }

            $exists = \App\Models\Konsinyasi\ConsignmentItem::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('supplier_id', $supplierId)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'product_id',
                    'Kombinasi produk dan supplier ini sudah terdaftar sebagai barang konsinyasi.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'consignment_price.lte' => 'Harga konsinyasi tidak boleh lebih besar dari harga jual.',
            'received_date.required_if' => 'Tanggal terima wajib diisi jika ada kuantitas awal.',
        ];
    }
}