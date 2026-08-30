<?php

namespace App\Http\Requests\Jualan\DeliveryNotes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryNoteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:draft,dikirim,terkirim,dibatalkan',
        ];
    }
}