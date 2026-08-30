<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DashboardActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'limit' => $this->input('limit', 10),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'limit' => ['integer', 'min:1', 'max:50'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'module' => ['nullable', 'in:sales,stock,user,store'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'limit.integer' => 'Limit harus berupa angka.',
            'limit.min' => 'Limit minimal 1.',
            'limit.max' => 'Limit maksimal 50.',
            'date.date_format' => 'Tanggal harus menggunakan format Y-m-d.',
            'module.in' => 'Modul aktivitas tidak valid.',
            'user_id.exists' => 'User aktivitas tidak ditemukan.',
        ];
    }
}
