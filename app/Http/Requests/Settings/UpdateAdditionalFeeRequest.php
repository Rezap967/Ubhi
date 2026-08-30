<?php

namespace App\Http\Requests\Settings;

use App\Models\Settings\AdditionalFeeSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateAdditionalFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        

        return true; // otorisasi role sudah ditangani middleware 'role:owner'
    }


    public function rules(): array
    {
        $storeId = $this->user()?->store_id;
        $id = $this->route('id') ?? $this->route('additional_fee');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('additional_fee_settings', 'name')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($id)
            ],
            'fee_type' => ['required', Rule::in([AdditionalFeeSetting::TYPE_FIXED, AdditionalFeeSetting::TYPE_PERCENT])],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([AdditionalFeeSetting::STATUS_ACTIVE, AdditionalFeeSetting::STATUS_INACTIVE])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $feeType = $this->input('fee_type');
            $amount = $this->input('amount');

            if ($feeType === AdditionalFeeSetting::TYPE_PERCENT && is_numeric($amount) && (float)$amount > 100) {
                $validator->errors()->add('amount', 'Nilai persentase tidak boleh lebih dari 100%.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama biaya wajib diisi.',
            'name.max' => 'Nama biaya maksimal 100 karakter.',
            'name.unique' => 'Nama biaya sudah digunakan di toko ini.',
            'fee_type.required' => 'Tipe biaya wajib dipilih.',
            'fee_type.in' => 'Tipe biaya tidak valid.',
            'amount.required' => 'Nilai biaya wajib diisi.',
            'amount.numeric' => 'Nilai biaya harus berupa angka.',
            'amount.min' => 'Nilai biaya tidak boleh kurang dari 0.',
            'status.in' => 'Status tidak valid.',
            'sort_order.integer' => 'Urutan tampil harus berupa angka.',
            'sort_order.min' => 'Urutan tampil tidak boleh kurang dari 0.',
            'sort_order.max' => 'Urutan tampil maksimal 255.',
        ];
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Forbidden. Hanya Owner yang dapat mengelola biaya tambahan.',
            'errors' => new \stdClass()
        ], 403));
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422));
    }
}