<?php

namespace App\Http\Requests\Belanja\PurchaseReturns;

use App\Models\Belanja\PurchaseReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PurchaseReturn::class);
    }

    public function rules(): array
    {
        $storeId = $this->user()?->profile?->store_id;

        return [
            'purchase_order_id' => [
                'required',
                Rule::exists('purchase_orders', 'id')
                    ->where('store_id', $storeId)
                    ->whereNull('deleted_at'),
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
            'items.*.purchase_order_item_id' => [
                'required',
                Rule::exists('purchase_order_items', 'id'),
            ],
            'items.*.qty_returned' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.reason' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'PO asal wajib dipilih.',
            'purchase_order_id.exists' => 'PO asal tidak ditemukan atau tidak aktif di toko Anda.',
            'items.required' => 'Minimal satu item retur harus dipilih.',
            'items.min' => 'Minimal satu item retur harus dipilih.',
            'items.*.purchase_order_item_id.required' => 'ID Item Purchase Order wajib diisi.',
            'items.*.purchase_order_item_id.exists' => 'Item Purchase Order tidak ditemukan.',
            'items.*.qty_returned.required' => 'Qty retur wajib diisi.',
            'items.*.qty_returned.integer' => 'Qty retur harus berupa angka.',
            'items.*.qty_returned.min' => 'Qty retur minimal 1.',
        ];
    }
}
