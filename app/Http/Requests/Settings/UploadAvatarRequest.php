<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'File avatar wajib diunggah.',
            'avatar.image' => 'File harus berupa gambar.',
            'avatar.mimes' => 'Format avatar harus jpg, jpeg, png, atau webp.',
            'avatar.max' => 'Ukuran avatar maksimal 1MB.',
        ];
    }
}
