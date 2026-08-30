<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TrialBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sortByMap = [
            'account_code' => 'code',
            'account_name' => 'name',
            'account_type' => 'type',
            'debit' => 'mutation_debit',
            'credit' => 'mutation_credit',
        ];

        $this->merge([
            'month' => $this->input('month') ?: $this->input('period_key'),
            'date_start' => $this->parseDate($this->input('date_start')),
            'date_end' => $this->parseDate($this->input('date_end')),
            'type' => $this->input('type') ?? $this->normalizeFilterValue($this->input('account_type'), 'all'),
            'status' => $this->input('status') ?? $this->normalizeFilterValue($this->input('account_status'), 'all'),
            'sort_by' => $sortByMap[$this->input('sort_by')] ?? $this->input('sort_by'),
            'sort_direction' => $this->input('sort_direction') ?? $this->input('sort_dir'),
            'show_zero_balance' => $this->normalizeBoolean(
                $this->input('show_zero_balance') ?? $this->input('include_zero_balance')
            ),
            'group_by_type' => $this->normalizeBoolean($this->input('group_by_type')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'search' => ['nullable', 'string', 'max:100'],
            'account_id' => ['nullable', 'uuid'],
            'type' => ['nullable', Rule::in(['all', 'asset', 'liability', 'equity', 'revenue', 'expense'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'sort_by' => ['nullable', Rule::in([
                'code',
                'name',
                'type',
                'normal_balance',
                'mutation_debit',
                'mutation_credit',
                'total_debit',
                'total_credit',
                'ending_balance',
            ])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'show_zero_balance' => ['nullable', 'boolean'],
            'group_by_type' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function messages(): array
    {
        return [
            'month.date_format' => 'Bulan harus menggunakan format YYYY-MM.',
            'date_start.date' => 'Tanggal mulai tidak valid.',
            'date_end.date' => 'Tanggal selesai tidak valid.',
            'date_end.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
            'search.string' => 'Pencarian akun harus berupa teks.',
            'search.max' => 'Pencarian akun maksimal 100 karakter.',
            'account_id.uuid' => 'Akun yang dipilih tidak valid.',
            'type.in' => 'Tipe akun tidak valid.',
            'status.in' => 'Status akun tidak valid.',
            'sort_by.in' => 'Kolom pengurutan tidak valid.',
            'sort_direction.in' => 'Arah pengurutan tidak valid.',
            'show_zero_balance.boolean' => 'Pilihan tampilkan saldo nol tidak valid.',
            'group_by_type.boolean' => 'Pilihan pengelompokan tipe tidak valid.',
            'per_page.integer' => 'Jumlah baris harus berupa angka.',
            'per_page.in' => 'Jumlah baris harus 10, 25, 50, atau 100.',
        ];
    }

    protected function normalizeBoolean(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '1' => true,
            'false', '0' => false,
            default => $value,
        };
    }

    protected function normalizeFilterValue(mixed $value, string $default): mixed
    {
        return in_array($value, [null, '', 'semua'], true) ? $default : $value;
    }

    protected function parseDate(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, trim($value))->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return $value;
    }
}
