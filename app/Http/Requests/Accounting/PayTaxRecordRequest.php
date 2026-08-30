<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayTaxRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = active_store_id();

        return ['tax_account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')->where('store_id', $storeId)->where('is_active', true)], 'counter_account_id' => ['required', 'uuid', 'different:tax_account_id', Rule::exists('accounts', 'id')->where('store_id', $storeId)->where('is_active', true)], 'paid_at' => ['nullable', 'date']];
    }

    public function messages(): array
    {
        return ['tax_account_id.required' => 'Akun pajak wajib dipilih saat status dibayar.', 'counter_account_id.required' => 'Akun lawan wajib dipilih saat status dibayar.', 'counter_account_id.different' => 'Akun pajak dan akun lawan tidak boleh sama.'];
    }
}
