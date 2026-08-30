<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfitLossAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = (string) $this->type;
        $amount = (float) $this->amount;
        $amountLabel = $this->formatCurrency($amount);

        return [
            'account_id' => $this->account_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $type,
            'type_label' => $type === 'revenue' ? 'Pendapatan' : 'Beban',
            'normal_balance' => $this->normal_balance,
            'total_debit' => (float) $this->total_debit,
            'total_credit' => (float) $this->total_credit,
            'amount' => $amount,
            'amount_label' => $amountLabel,
            'is_active' => (bool) $this->is_active,
            'nama' => $this->name,
            'kode' => $this->code,
            'nilai' => $amount,
            'nilai_label' => $amountLabel,
        ];
    }

    protected function formatCurrency(float $amount): string
    {
        return $amount < 0
            ? '-Rp '.number_format(abs($amount), 0, ',', '.')
            : 'Rp '.number_format($amount, 0, ',', '.');
    }
}
