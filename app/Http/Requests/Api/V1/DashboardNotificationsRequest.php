<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DashboardNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['page' => $this->input('page', 1), 'per_page' => $this->input('per_page', 10)]);
    }

    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:50'],
            'is_read' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string'],
        ];
    }
}
