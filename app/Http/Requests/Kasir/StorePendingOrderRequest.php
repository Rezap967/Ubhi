<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class StorePendingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|array<string, mixed>>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:1'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pesanan atau meja wajib diisi.',
            'name.string' => 'Nama pesanan harus berupa teks.',
            'name.max' => 'Nama pesanan maksimal 100 karakter.',
            'customer_name.string' => 'Nama pelanggan harus berupa teks.',
            'customer_name.max' => 'Nama pelanggan maksimal 100 karakter.',
            'note.string' => 'Catatan harus berupa teks.',
            'note.max' => 'Catatan maksimal 500 karakter.',
            'items.required' => 'Item pesanan wajib diisi.',
            'items.array' => 'Format item pesanan tidak valid.',
            'items.min' => 'Minimal harus ada 1 item dalam pesanan.',
            'items.*.product_id.required_with' => 'ID produk pada item wajib diisi.',
            'items.*.product_id.integer' => 'ID produk item harus berupa angka.',
            'items.*.product_id.min' => 'ID produk tidak valid.',
            'items.*.quantity.required_with' => 'Kuantitas item wajib diisi.',
            'items.*.quantity.numeric' => 'Kuantitas item harus berupa angka.',
            'items.*.quantity.min' => 'Kuantitas item minimal 1.',
            'subtotal.required' => 'Subtotal wajib diisi.',
            'subtotal.numeric' => 'Subtotal harus berupa angka.',
            'subtotal.min' => 'Subtotal tidak boleh kurang dari 0.',
            'tax.required' => 'Pajak wajib diisi.',
            'tax.numeric' => 'Pajak harus berupa angka.',
            'tax.min' => 'Pajak tidak boleh kurang dari 0.',
            'total.required' => 'Total wajib diisi.',
            'total.numeric' => 'Total harus berupa angka.',
            'total.min' => 'Total tidak boleh kurang dari 0.',
        ];
    }
}
