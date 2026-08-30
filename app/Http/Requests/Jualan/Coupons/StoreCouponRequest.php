<?php

namespace App\Http\Requests\Jualan\Coupons;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy
    }

    /**
     * Set sensible defaults before validation runs, so NOT NULL columns
     * in the coupons table (per_customer_limit, status) never receive
     * a raw null even when the user leaves the field empty in the form.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_customer_limit' => $this->input('per_customer_limit') ?: 1,
            'status' => $this->input('status') ?: 'aktif',
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:coupons,code',
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'discount_type' => [
                'required',
                'string',
                'in:persentase,nominal',
            ],
            'discount_value' => [
                'required',
                'numeric',
                'min:0',
            ],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'total_quota' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:aktif,nonaktif,berakhir'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation: if discount_type is persentase, discount_value <= 100
            if ($this->input('discount_type') === 'persentase' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Diskon persentase tidak boleh melebihi 100%.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode kupon wajib diisi.',
            'code.unique' => 'Kode kupon sudah ada.',
            'discount_type.required' => 'Tipe diskon wajib dipilih.',
            'discount_type.in' => 'Tipe diskon tidak valid.',
            'discount_value.required' => 'Nilai diskon wajib diisi.',
            'discount_value.min' => 'Nilai diskon harus 0 atau lebih.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date_format' => 'Format tanggal mulai tidak valid (gunakan YYYY-MM-DD).',
            'end_date.required' => 'Tanggal berakhir wajib diisi.',
            'end_date.date_format' => 'Format tanggal berakhir tidak valid (gunakan YYYY-MM-DD).',
            'end_date.after_or_equal' => 'Tanggal berakhir harus sama atau setelah tanggal mulai.',
            'total_quota.min' => 'Kuota harus minimal 1.',
            'per_customer_limit.required' => 'Batas per pelanggan wajib diisi.',
            'per_customer_limit.min' => 'Batas per pelanggan harus minimal 1.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
        ];
    }
}
