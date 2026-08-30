<?php

namespace App\Http\Requests\Jualan\Orders;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'payment_status' => ['required', 'in:belum_bayar,parsial,lunas'],
            'delivery_method' => ['required', 'in:ambil_ditempat,pengiriman'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal, silakan periksa data inputan Anda.',
            'errors' => $validator->errors(),
            'code' => 'ORDER_VALIDATION_ERROR',
        ], 422));
    }
}