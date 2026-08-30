<?php

namespace App\Http\Requests\Jualan\Orders;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:menunggu,diproses,selesai,dibatalkan'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi status gagal.',
            'errors' => $validator->errors(),
            'code' => 'ORDER_VALIDATION_ERROR',
        ], 422));
    }
}