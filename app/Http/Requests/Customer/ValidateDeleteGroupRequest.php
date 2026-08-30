<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ValidateDeleteGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('group'));
    }

    public function rules(): array
    {
        return [
            'confirm' => ['sometimes', 'boolean'],
        ];
    }
}
