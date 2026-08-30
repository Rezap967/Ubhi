<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('login') && ! $this->has('identity')) {
            $this->merge(['identity' => $this->input('login')]);
        }
    }

    public function rules(): array
    {
        return [
            'identity' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'identity.required' => 'Email atau username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ];
    }
}
