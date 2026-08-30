<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = active_store_id();

        return ['transaction_no' => ['nullable', 'string', 'max:50'], 'tax_date' => ['required', 'date'], 'tax_rate_id' => ['nullable', 'uuid', Rule::exists('tax_rates', 'id')->where('store_id', $storeId)->where('is_active', true)], 'tax_type' => ['required', Rule::in(['ppn_keluaran', 'ppn_masukan', 'pph_final_umkm', 'pph', 'other'])], 'tax_name' => ['nullable', 'string', 'max:120'], 'tax_base' => ['required', 'numeric', 'min:0'], 'tax_rate' => ['nullable', 'numeric', 'min:0'], 'tax_amount' => ['nullable', 'numeric', 'min:0'], 'status' => ['nullable', Rule::in(['pending', 'paid'])], 'tax_account_id' => ['nullable', 'required_if:status,paid', 'uuid', Rule::exists('accounts', 'id')->where('store_id', $storeId)->where('is_active', true)], 'counter_account_id' => ['nullable', 'required_if:status,paid', 'uuid', 'different:tax_account_id', Rule::exists('accounts', 'id')->where('store_id', $storeId)->where('is_active', true)], 'source_type' => ['nullable', 'string', 'max:100'], 'source_id' => ['nullable', 'uuid'], 'reference_no' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['transaction_no' => trim((string) $this->input('transaction_no', '')) ?: null, 'tax_name' => trim((string) $this->input('tax_name', '')) ?: null, 'status' => $this->input('status', 'pending')]);
    }

    public function messages(): array
    {
        return ['tax_date.required' => 'Tanggal pajak wajib diisi.', 'tax_type.required' => 'Jenis pajak wajib dipilih.', 'tax_base.required' => 'DPP atau omzet wajib diisi.', 'tax_base.min' => 'DPP atau omzet tidak boleh kurang dari nol.', 'tax_amount.min' => 'Nominal pajak tidak boleh kurang dari nol.', 'counter_account_id.different' => 'Akun pajak dan akun lawan tidak boleh sama.', 'tax_account_id.required_if' => 'Akun pajak wajib dipilih saat status dibayar.', 'counter_account_id.required_if' => 'Akun lawan wajib dipilih saat status dibayar.'];
    }
}
