<?php

namespace App\Http\Requests\Jualan\Coupons;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCouponsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy
    }

    public function rules(): array
    {
        return [
            'name_prefix' => ['nullable', 'string', 'max:50'],
            'code_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'discount_type' => ['required', 'string', 'in:persentase,nominal'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'per_coupon_limit' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'total_quota' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If discount_type is persentase, discount_value <= 100
            if ($this->input('discount_type') === 'persentase' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Diskon persentase tidak boleh melebihi 100%.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'code_prefix.required' => 'Prefiks kode wajib diisi.',
            'code_prefix.regex' => 'Prefiks hanya boleh huruf besar dan angka (0-9, A-Z).',
            'discount_type.required' => 'Tipe diskon wajib dipilih.',
            'discount_value.required' => 'Nilai diskon wajib diisi.',
            'discount_value.min' => 'Nilai diskon harus 0 atau lebih.',
            'quantity.required' => 'Jumlah kupon wajib diisi.',
            'quantity.min' => 'Jumlah kupon harus minimal 1.',
            'quantity.max' => 'Jumlah kupon harus antara 1 hingga 500.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date_format' => 'Format tanggal mulai tidak valid (gunakan YYYY-MM-DD).',
            'end_date.required' => 'Tanggal berakhir wajib diisi.',
            'end_date.date_format' => 'Format tanggal berakhir tidak valid (gunakan YYYY-MM-DD).',
            'end_date.after_or_equal' => 'Tanggal berakhir harus sama atau setelah tanggal mulai.',
        ];
    }
}
