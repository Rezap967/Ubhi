<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOpnameItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'physical_stock' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'physical_stock.required' => 'Stok fisik wajib diisi.',
            'physical_stock.numeric' => 'Stok fisik harus berupa angka.',
            'physical_stock.min' => 'Stok fisik tidak boleh negatif.',
        ];
    }
}
