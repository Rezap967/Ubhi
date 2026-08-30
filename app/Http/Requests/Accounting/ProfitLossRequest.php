<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ProfitLossRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_start' => $this->parseDate($this->input('date_start'), 'date_start'),
            'date_end' => $this->parseDate($this->input('date_end'), 'date_end'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_start.date' => 'Tanggal mulai tidak valid.',
            'date_end.date' => 'Tanggal selesai tidak valid.',
            'date_end.after_or_equal' => 'Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.',
        ];
    }

    protected function parseDate(mixed $value, string $field): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, trim($value))->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        $this->merge([$field => $value]);

        return $value;
    }
}
