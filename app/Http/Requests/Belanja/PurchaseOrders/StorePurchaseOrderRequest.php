<?php

namespace App\Http\Requests\Belanja\PurchaseOrders;

use App\Models\Belanja\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PurchaseOrder::class);
    }

    public function rules(): array
    {
        $storeId = $this->user()?->profile?->store_id;

        return [
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where('status', 'aktif'),
            ],
            'jenis_pembayaran' => [
                'required',
                Rule::in(['Kredit', 'Tunai']),
            ],
            'estimasi_tiba' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')
                    ->where('status', 'aktif')
                    ->where('store_id', $storeId),
            ],
            'items.*.qty_ordered' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'items.*.expiry_date' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier tidak valid atau tidak aktif.',
            'jenis_pembayaran.required' => 'Jenis pembayaran wajib dipilih.',
            'jenis_pembayaran.in' => 'Jenis pembayaran harus Kredit atau Tunai.',
            'estimasi_tiba.date' => 'Estimasi tiba harus berupa tanggal yang valid.',
            'estimasi_tiba.after_or_equal' => 'Estimasi tiba tidak boleh sebelum hari ini.',
            'items.required' => 'Minimal satu item produk wajib ditambahkan.',
            'items.min' => 'Minimal satu item produk wajib ditambahkan.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
            'items.*.product_id.exists' => 'Produk tidak valid atau tidak aktif di toko ini.',
            'items.*.qty_ordered.required' => 'Qty pesan wajib diisi.',
            'items.*.qty_ordered.integer' => 'Qty pesan harus berupa angka.',
            'items.*.qty_ordered.min' => 'Qty pesan minimal 1.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
            'items.*.unit_price.numeric' => 'Harga satuan harus berupa angka.',
            'items.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
            'items.*.expiry_date.date' => 'Tanggal kedaluwarsa harus berupa tanggal yang valid.',
        ];
    }
}
