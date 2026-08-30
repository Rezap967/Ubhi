<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class SendStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.required' => 'Konfirmasi pengiriman wajib disertakan.',
            'confirm.accepted' => 'Konfirmasi pengiriman harus bernilai true.',
        ];
    }
}
