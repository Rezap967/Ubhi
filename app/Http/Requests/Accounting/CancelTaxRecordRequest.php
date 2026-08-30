<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class CancelTaxRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cancel_reason' => ['required', 'string', 'max:1000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cancel_reason' => trim((string) $this->input('cancel_reason', ''))]);
    }
}
