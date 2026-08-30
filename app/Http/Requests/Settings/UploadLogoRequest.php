<?php

// app/Http/Requests/Settings/UploadLogoRequest.php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'File logo wajib diunggah.',
            'logo.image' => 'File harus berupa gambar.',
            'logo.mimes' => 'Format logo harus jpg, jpeg, png, atau webp.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
        ];
    }
}
