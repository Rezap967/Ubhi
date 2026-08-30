<?php

namespace App\Http\Requests\Kasir\Discounts;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via role check
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:100'],
            'coupon_code' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_id.required' => 'Device ID wajib diisi.',
            'coupon_code.required' => 'Kode kupon wajib diisi.',
            'coupon_code.max' => 'Kode kupon terlalu panjang.',
        ];
    }
}
