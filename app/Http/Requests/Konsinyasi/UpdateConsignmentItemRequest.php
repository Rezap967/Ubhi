<?php

namespace App\Http\Requests\Konsinyasi;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsignmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->store_id !== null;
    }

    public function rules(): array
    {
        return [
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'consignment_price' => ['sometimes', 'required', 'numeric', 'min:0', 'lte:selling_price'],
            'commission_type' => ['sometimes', 'required', 'in:margin,percentage,fixed'],
            'commission_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', 'in:active,inactive,closed'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'consignment_price.lte' => 'Harga konsinyasi tidak boleh lebih besar dari harga jual.',
        ];
    }
}