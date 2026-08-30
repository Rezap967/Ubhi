<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Izinkan device_id dari query string pada DELETE request
        if ($this->query('device_id') && ! $this->input('device_id')) {
            $this->merge(['device_id' => $this->query('device_id')]);
        }
    }
}
