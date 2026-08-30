<?php

namespace App\Http\Requests\Jualan\SalesReturn;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'refund_method' => 'required|in:tunai,saldo_toko,tukar_barang',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.qty_returned' => 'required|numeric|min:0.001',
            'items.*.condition' => 'required|in:baik,rusak,cacat,kadaluarsa',
        ];
    }
}