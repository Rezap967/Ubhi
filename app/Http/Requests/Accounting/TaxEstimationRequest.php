<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class TaxEstimationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_start' => $this->parseDate($this->input('date_start')),
            'date_end' => $this->parseDate($this->input('date_end')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:all,pending,paid,cancelled'],
            'period' => ['nullable', 'string', 'max:50'],
            'month' => ['nullable', 'date_format:Y-m'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status tidak valid.',
            'month.date_format' => 'Format periode tidak valid.',
            'date_start.date' => 'Tanggal mulai tidak valid.',
            'date_end.date' => 'Tanggal selesai tidak valid.',
            'date_end.after_or_equal' => 'Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.',
        ];
    }

    protected function parseDate(mixed $value): mixed
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

        return $value;
    }
}
