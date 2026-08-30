<?php

namespace App\Http\Requests\Jualan\SalesReturn;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesReturnStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:disetujui,ditolak,dibatalkan',
        ];
    }
}