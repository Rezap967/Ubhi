<?php

// app/Http/Requests/Products/StoreProductRequest.php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }

        if (is_string($this->sku)) {
            $this->merge(['sku' => trim($this->sku) ?: null]);
        }

        if (is_string($this->barcode)) {
            $this->merge(['barcode' => trim($this->barcode) ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['bail', 'nullable', 'string'],
            'category_id' => ['bail', 'required', 'integer', 'exists:product_categories,id,deleted_at,NULL'],
            'brand_id' => ['bail', 'nullable', 'integer', 'exists:brands,id,deleted_at,NULL'],
            'unit_id' => ['bail', 'required', 'integer', 'exists:product_units,id,deleted_at,NULL'],
            'type' => ['bail', 'required', 'in:single,variant,bundle'],
            'sell_price' => ['bail', 'required', 'numeric', 'min:1'],
            'buy_price' => ['bail', 'required', 'numeric', 'min:0'],
            'initial_stock' => ['bail', 'nullable', 'integer', 'min:0'],
            'sku' => ['bail', 'nullable', 'string', 'max:100', 'unique:products,sku,NULL,id,deleted_at,NULL'],
            'barcode' => ['bail', 'nullable', 'string', 'max:100', 'unique:products,barcode,NULL,id,deleted_at,NULL'],
            'status' => ['bail', 'nullable', 'in:aktif,nonaktif'],
            'satuans' => ['bail', 'nullable', 'array'],
            'satuans.*.satuan' => ['bail', 'required', 'integer', 'exists:product_units,id,deleted_at,NULL'],
            'satuans.*.sku' => ['bail', 'nullable', 'string', 'max:100'],
            'satuans.*.barcode' => ['bail', 'nullable', 'string', 'max:100'],
            'satuans.*.hargaJual' => ['bail', 'required', 'numeric', 'min:1'],
            'satuans.*.hargaBeli' => ['bail', 'nullable', 'numeric', 'min:0'],
            'satuans.*.stokAwal' => ['bail', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama produk wajib diisi.',
            'name.max' => 'Nama produk maksimal 255 karakter.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid atau tidak aktif.',
            'brand_id.exists' => 'Merek yang dipilih tidak valid.',
            'unit_id.required' => 'Satuan wajib dipilih.',
            'unit_id.exists' => 'Satuan yang dipilih tidak valid atau tidak aktif.',
            'type.required' => 'Tipe produk wajib diisi.',
            'type.in' => 'Tipe produk harus salah satu dari: single, variant, bundle.',
            'sell_price.required' => 'Harga jual wajib diisi.',
            'sell_price.numeric' => 'Harga jual harus berupa angka.',
            'satuans.*.satuan.required' => 'Satuan pada varian wajib dipilih.',
            'satuans.*.satuan.exists' => 'Satuan pada varian tidak valid.',
            'satuans.*.hargaJual.required' => 'Harga jual pada varian wajib diisi.',
            'satuans.*.hargaJual.numeric' => 'Harga jual pada varian harus berupa angka.',
            'buy_price.required' => 'Harga beli wajib diisi.',
            'buy_price.min' => 'Harga beli tidak boleh negatif.',
            'initial_stock.min' => 'Stok awal tidak boleh negatif.',
            'sku.unique' => 'SKU sudah digunakan produk lain di toko ini.',
            'sku.max' => 'SKU maksimal 100 karakter.',
            'barcode.unique' => 'Barcode sudah digunakan produk lain di toko ini.',
            'barcode.max' => 'Barcode maksimal 100 karakter.',
            'status.in' => 'Status harus salah satu dari: aktif, nonaktif.',
        ];
    }
}
