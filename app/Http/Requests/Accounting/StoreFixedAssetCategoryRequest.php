<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $storeId = active_store_id();

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('fixed_asset_categories', 'code')->where('store_id', $storeId)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'asset_account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')->where('store_id', $storeId)->where('is_active', true)->where('type', 'asset')],
            'accumulated_depreciation_account_id' => ['nullable', 'required_unless:depreciation_method,none', 'uuid', Rule::exists('accounts', 'id')->where('store_id', $storeId)->where('is_active', true)->where('type', 'asset')],
            'depreciation_expense_account_id' => ['nullable', 'required_unless:depreciation_method,none', 'uuid', Rule::exists('accounts', 'id')->where('store_id', $storeId)->where('is_active', true)->where('type', 'expense')],
            'default_useful_life_months' => ['nullable', 'required_unless:depreciation_method,none', 'integer', 'min:1', 'max:1200'],
            'depreciation_method' => ['required', Rule::in(['straight_line', 'declining_balance', 'units_of_production', 'none'])],
            'depreciation_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'residual_value_type' => ['nullable', Rule::in(['amount', 'percentage', 'none'])],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) ($this->input('code') ?? $this->input('category_code')))),
            'name' => trim((string) ($this->input('name') ?? $this->input('category_name'))),
            'residual_value_type' => $this->input('residual_value_type', 'none'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode kategori wajib diisi.',
            'code.unique' => 'Kode kategori sudah digunakan.',
            'name.required' => 'Nama kategori wajib diisi.',
            'asset_account_id.required' => 'Akun aset wajib dipilih.',
            'asset_account_id.exists' => 'Akun aset tidak valid atau bukan milik toko aktif.',
            'depreciation_method.in' => 'Metode depresiasi tidak valid.',
        ];
    }
}
