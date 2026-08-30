<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrialBalanceRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = (string) data_get($this->resource, 'type');
        $normalBalance = (string) data_get($this->resource, 'normal_balance');
        $isActive = (bool) data_get($this->resource, 'is_active');

        return [
            'account_id' => data_get($this->resource, 'account_id'),
            'code' => data_get($this->resource, 'code'),
            'name' => data_get($this->resource, 'name'),
            'account_code' => data_get($this->resource, 'code'),
            'account_name' => data_get($this->resource, 'name'),
            'type' => $type,
            'account_type' => $type,
            'type_label' => self::typeLabel($type),
            'normal_balance' => $normalBalance,
            'normal_balance_label' => $normalBalance === 'credit' ? 'Kredit' : 'Debit',
            'status' => $isActive ? 'active' : 'inactive',
            'status_label' => $isActive ? 'Aktif' : 'Nonaktif',
            'mutation_debit' => (float) data_get($this->resource, 'mutation_debit', 0),
            'mutation_credit' => (float) data_get($this->resource, 'mutation_credit', 0),
            'total_debit' => (float) data_get($this->resource, 'total_debit', 0),
            'total_credit' => (float) data_get($this->resource, 'total_credit', 0),
            'ending_balance' => (float) data_get($this->resource, 'ending_balance', 0),
        ];
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'asset' => 'Aset',
            'liability' => 'Kewajiban',
            'equity' => 'Ekuitas',
            'revenue' => 'Pendapatan',
            'expense' => 'Beban',
            default => $type,
        };
    }
}
