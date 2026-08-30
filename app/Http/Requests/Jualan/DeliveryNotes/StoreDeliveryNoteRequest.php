<?php

namespace App\Http\Requests\Jualan\DeliveryNotes;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => 'required|in:transaksi_penjualan,order_pelanggan',
            'order_id' => 'required_if:source_type,transaksi_penjualan|nullable|exists:orders,id',
            'customer_order_id' => 'required_if:source_type,order_pelanggan|nullable|exists:orders,id',
            'recipient_name' => 'required|string|max:255',
            'recipient_address' => 'required|string|max:1000',
            'delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'nullable|exists:order_items,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ];
    }
}