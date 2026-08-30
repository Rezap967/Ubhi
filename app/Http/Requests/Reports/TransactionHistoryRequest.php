<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TransactionHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_start' => ['nullable', 'date'],
            'date_end' => [
                'nullable',
                'date',
                'after_or_equal:date_start',
                function ($attr, $value, $fail) {
                    $dateStart = $this->input('date_start');
                    if ($dateStart && $value) {
                        try {
                            $start = Carbon::parse($dateStart);
                            $end = Carbon::parse($value);
                            if ($start->diffInDays($end) > 365) {
                                $fail('Rentang tanggal tidak boleh lebih dari 1 tahun.');
                            }
                        } catch (\Exception $e) {
                            // Let the date validator handle invalid date string
                        }
                    }
                },
            ],
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'receipt_no' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in(['transaction_at', 'receipt_no', 'grand_total', 'status'])],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Default to current month if either date_start or date_end is missing
        if (! $this->filled('date_start') || ! $this->filled('date_end')) {
            $this->merge([
                'date_start' => $this->input('date_start', now()->startOfMonth()->toDateString()),
                'date_end' => $this->input('date_end', now()->endOfMonth()->toDateString()),
            ]);
        }
    }

    public function filters(): array
    {
        return $this->only([
            'date_start', 'date_end', 'cashier_id', 'payment_method',
            'status', 'customer_id', 'receipt_no', 'per_page',
            'sort_by', 'sort_order',
        ]);
    }
}
