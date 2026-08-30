<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy dihandle di controller
    }

    public function rules(): array
    {
        $storeId = $this->resolveStoreId();

        $rules = [
            'from_warehouse_id' => [
                'required',
                'numeric',
            ],
            'to_warehouse_id' => [
                'required',
                'numeric',
                'different:from_warehouse_id',
            ],
            'transfer_date' => [
                'required',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.product_id' => [
                'required',
                'distinct',
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'items.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];

        // Validasi database scoped ke store aktif user
        if ($storeId !== null) {
            $rules['from_warehouse_id'][] = Rule::exists('warehouses', 'id')
                ->where('store_id', $storeId)
                ->whereNull('deleted_at');

            $rules['to_warehouse_id'][] = Rule::exists('warehouses', 'id')
                ->where('store_id', $storeId)
                ->whereNull('deleted_at');

            $rules['items.*.product_id'][] = Rule::exists('products', 'id')
                ->where('store_id', $storeId)
                ->where('status', 'aktif')
                ->whereNull('deleted_at');
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'from_warehouse_id.required' => 'Gudang asal wajib dipilih.',
            'from_warehouse_id.exists' => 'Gudang asal tidak ditemukan atau tidak aktif.',
            'to_warehouse_id.required' => 'Gudang tujuan wajib dipilih.',
            'to_warehouse_id.different' => 'Gudang asal dan gudang tujuan tidak boleh sama.',
            'to_warehouse_id.exists' => 'Gudang tujuan tidak ditemukan atau tidak aktif.',
            'transfer_date.required' => 'Tanggal transfer wajib diisi.',
            'transfer_date.date' => 'Format tanggal transfer tidak valid.',
            'items.required' => 'Minimal harus ada 1 item transfer.',
            'items.min' => 'Minimal harus ada 1 item transfer.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
            'items.*.product_id.distinct' => 'Produk tidak boleh duplikat dalam satu transfer.',
            'items.*.product_id.exists' => 'Produk tidak ditemukan atau tidak aktif.',
            'items.*.quantity.required' => 'Jumlah transfer wajib diisi.',
            'items.*.quantity.numeric' => 'Jumlah transfer harus berupa angka.',
            'items.*.quantity.gt' => 'Jumlah transfer harus lebih dari 0.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ];
    }

    /**
     * Resolve store_id user secara aman tanpa throw error validasi.
     */
    private function resolveStoreId(): ?int
    {
        try {
            return $this->user()?->activeStoreId();
        } catch (\RuntimeException) {
            return null;
        }
    }
}
