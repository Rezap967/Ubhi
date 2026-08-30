<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalEntryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'reversed', 'void'])],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Pencarian jurnal harus berupa teks.',
            'search.max' => 'Pencarian jurnal maksimal 100 karakter.',
            'status.in' => 'Status jurnal tidak valid.',
            'date_start.date' => 'Tanggal awal tidak valid.',
            'date_end.date' => 'Tanggal akhir tidak valid.',
            'date_end.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal awal.',
            'per_page.integer' => 'Jumlah data per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah data per halaman minimal 1.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
