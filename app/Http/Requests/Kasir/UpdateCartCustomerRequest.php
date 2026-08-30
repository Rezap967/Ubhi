<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_id.required' => 'Device ID wajib disertakan.',
            'customer_id.exists' => 'Pelanggan tidak ditemukan.',
        ];
    }
}
