<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class ResolveBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Dihandle middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Barcode via path param, we can validate it by merging route params
            'barcode' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'barcode' => $this->route('barcode'),
        ]);
    }
}
