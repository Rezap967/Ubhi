<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceSheetAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = (string) data_get($this->resource, 'type');
        $amount = (float) data_get($this->resource, 'amount', 0);
        $totalDebit = (float) data_get($this->resource, 'total_debit', 0);
        $totalCredit = (float) data_get($this->resource, 'total_credit', 0);

        return [
            'account_id' => data_get($this->resource, 'account_id'),
            'code' => data_get($this->resource, 'code'),
            'name' => data_get($this->resource, 'name'),
            'type' => $type,
            'type_label' => self::typeLabel($type),
            'normal_balance' => data_get($this->resource, 'normal_balance'),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'amount' => $amount,
            'amount_label' => self::formatCurrency($amount),
            'is_active' => (bool) data_get($this->resource, 'is_active', true),
            // Aliases for frontend compatibility
            'nama' => data_get($this->resource, 'name'),
            'kode' => data_get($this->resource, 'code'),
            'nilai' => $amount,
            'nilai_label' => self::formatCurrency($amount),
        ];
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'asset' => 'Aktiva',
            'liability' => 'Kewajiban',
            'equity' => 'Modal',
            'revenue' => 'Pendapatan',
            'expense' => 'Beban',
            default => $type,
        };
    }

    public static function formatCurrency(float|int $amount): string
    {
        if ($amount < 0) {
            return '-Rp '.number_format(abs($amount), 0, ',', '.');
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
