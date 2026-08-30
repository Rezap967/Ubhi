<?php

namespace App\Http\Requests\Jualan\Coupons;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy
    }

    /**
     * Only coerce explicitly submitted empty values for fields that are required
     * by the database schema. If a field is omitted during a partial update,
     * we must leave it untouched instead of overwriting existing data.
     */
    protected function prepareForValidation(): void
    {
        $coercions = [
            'per_customer_limit' => 1,
            'status' => 'aktif',
        ];

        foreach ($coercions as $field => $default) {
            if ($this->exists($field) && ($this->input($field) === null || $this->input($field) === '')) {
                $this->merge([$field => $default]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:coupons,code,'.$this->route('coupon')->id,
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['nullable', 'string', 'in:persentase,nominal'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'total_quota' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:aktif,nonaktif,berakhir'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $coupon = $this->route('coupon');

            // Validate end_date >= start_date
            if ($this->input('start_date') && $this->input('end_date')) {
                if ($this->input('end_date') < $this->input('start_date')) {
                    $validator->errors()->add('end_date', 'Tanggal berakhir harus sama atau setelah tanggal mulai.');
                }
            }

            // Additional validation: if discount_type is persentase, discount_value <= 100
            if ($this->input('discount_type') === 'persentase' && $this->input('discount_value') !== null) {
                if ((float) $this->input('discount_value') > 100) {
                    $validator->errors()->add('discount_value', 'Diskon persentase tidak boleh melebihi 100%.');
                }
            }

            // Warn if trying to change restricted fields after coupon has been used
            if ($coupon->used_count > 0) {
                $restrictedFields = ['discount_type', 'discount_value'];

                foreach ($restrictedFields as $field) {
                    if ($this->input($field) !== null && $this->input($field) != $coupon->{$field}) {
                        // Don't add error here; let the service handle it with proper message
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode kupon wajib diisi.',
            'code.unique' => 'Kode kupon sudah ada.',
            'discount_type.in' => 'Tipe diskon tidak valid.',
            'discount_value.min' => 'Nilai diskon harus 0 atau lebih.',
            'start_date.date_format' => 'Format tanggal mulai tidak valid (gunakan YYYY-MM-DD).',
            'end_date.date_format' => 'Format tanggal berakhir tidak valid (gunakan YYYY-MM-DD).',
            'total_quota.min' => 'Kuota harus minimal 1.',
            'per_customer_limit.min' => 'Batas per pelanggan harus minimal 1.',
        ];
    }
}
