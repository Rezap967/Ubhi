<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');
        $referenceNo = $this->input('reference_no');
        $sourceType = $this->input('source_type');

        $this->merge([
            'description' => is_string($description) ? trim($description) : $description,
            'reference_no' => is_string($referenceNo) ? (trim($referenceNo) ?: null) : $referenceNo,
            'source_type' => is_string($sourceType) ? (trim($sourceType) ?: null) : $sourceType,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'journal_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['draft', 'posted'])],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', 'uuid'],
            'journal_no' => ['nullable', 'string', 'max:50'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where(
                    fn ($query) => $query
                        ->where('store_id', active_store_id())
                        ->where('is_active', true)
                        ->whereNull('deleted_at'),
                ),
            ],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lines = $this->input('lines', []);
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($lines as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);
                $totalDebit += $debit;
                $totalCredit += $credit;

                if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                    $validator->errors()->add(
                        "lines.{$index}",
                        'Setiap baris hanya boleh memiliki debit atau kredit lebih dari nol.',
                    );
                }
            }

            if ($totalDebit <= 0 || $totalCredit <= 0) {
                $validator->errors()->add('lines', 'Total debit dan kredit harus lebih dari nol.');
            } elseif (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $validator->errors()->add('lines', 'Total debit dan kredit harus sama.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'journal_date.required' => 'Tanggal jurnal wajib diisi.',
            'journal_date.date' => 'Tanggal jurnal tidak valid.',
            'description.required' => 'Deskripsi jurnal wajib diisi.',
            'description.max' => 'Deskripsi jurnal maksimal 1000 karakter.',
            'status.in' => 'Status jurnal tidak valid.',
            'reference_no.max' => 'Nomor referensi maksimal 100 karakter.',
            'source_type.max' => 'Tipe sumber maksimal 100 karakter.',
            'source_id.uuid' => 'ID sumber tidak valid.',
            'journal_no.max' => 'Nomor jurnal maksimal 50 karakter.',
            'lines.required' => 'Baris jurnal wajib diisi.',
            'lines.array' => 'Baris jurnal harus berupa array.',
            'lines.min' => 'Jurnal harus memiliki minimal dua baris.',
            'lines.*.account_id.required' => 'Akun pada baris jurnal wajib dipilih.',
            'lines.*.account_id.uuid' => 'ID akun pada baris jurnal tidak valid.',
            'lines.*.account_id.exists' => 'Akun jurnal tidak valid atau bukan milik toko aktif.',
            'lines.*.description.max' => 'Deskripsi baris maksimal 255 karakter.',
            'lines.*.debit.numeric' => 'Nilai debit harus berupa angka.',
            'lines.*.debit.min' => 'Nilai debit tidak boleh kurang dari nol.',
            'lines.*.credit.numeric' => 'Nilai kredit harus berupa angka.',
            'lines.*.credit.min' => 'Nilai kredit tidak boleh kurang dari nol.',
        ];
    }
}
