<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class PosCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi include_all sebelum validasi:
     * "true" / "1" / 1 → true | "false" / "0" / 0 → false
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('include_all')) {
            $this->merge([
                'include_all' => filter_var(
                    $this->input('include_all'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include_all' => ['nullable', 'boolean'],
        ];
    }
}
