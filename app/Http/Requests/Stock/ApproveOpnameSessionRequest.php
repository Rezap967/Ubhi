<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOpnameSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.required' => 'Konfirmasi persetujuan wajib diberikan.',
            'confirm.accepted' => 'Konfirmasi persetujuan tidak valid.',
        ];
    }
}
