<?php

namespace App\Http\Requests\Belanja\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchase_order') ?? $this->route('purchaseOrder');

        return $purchaseOrder && $this->user()->can('receive', $purchaseOrder);
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id'),
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
            'items.*.qty_received' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.integer' => 'ID gudang harus berupa angka.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak valid atau tidak ditemukan.',
            'items.required' => 'Minimal satu item penerimaan wajib disertakan.',
            'items.min' => 'Minimal satu item penerimaan wajib disertakan.',
            'items.*.purchase_order_item_id.required' => 'ID item wajib ada.',
            'items.*.purchase_order_item_id.exists' => 'ID item tidak ditemukan.',
            'items.*.qty_received.required' => 'Jumlah diterima wajib diisi.',
            'items.*.qty_received.integer' => 'Jumlah diterima harus berupa angka.',
            'items.*.qty_received.min' => 'Jumlah diterima minimal 1.',
        ];
    }
}
