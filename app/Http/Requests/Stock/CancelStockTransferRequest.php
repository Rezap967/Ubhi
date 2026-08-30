<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class CancelStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required', 'boolean', 'accepted'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.required' => 'Konfirmasi pembatalan wajib disertakan.',
            'confirm.accepted' => 'Konfirmasi pembatalan harus bernilai true.',
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ];
    }
}
