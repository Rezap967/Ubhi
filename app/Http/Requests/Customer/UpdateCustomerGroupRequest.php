<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('group'));
    }

    public function rules(): array
    {
        $storeId = $this->user()->store_id;

        // Mendapatkan ID dari parameter route 'group' (baik berupa Model Instance maupun ID)
        $group = $this->route('group');
        $groupId = is_object($group) ? $group->id : $group;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('customer_groups', 'name')
                    ->where('store_id', $storeId)
                    ->whereNull('deleted_at')
                    ->ignore($groupId),
            ],
            'description' => ['nullable', 'string'],
            'discount_type' => ['nullable', 'in:persen,nominal'],
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->input('discount_type') === 'persen' && $value > 100) {
                        $fail('Nilai diskon persen tidak boleh lebih dari 100.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama grup wajib diisi.',
            'name.unique' => 'Nama grup sudah digunakan di toko ini.',
            'name.max' => 'Nama grup maksimal 100 karakter.',
            'discount_type.in' => 'Tipe diskon harus persen atau nominal.',
            'discount_value.numeric' => 'Nilai diskon harus berupa angka.',
            'discount_value.min' => 'Nilai diskon tidak boleh negatif.',
        ];
    }
}
