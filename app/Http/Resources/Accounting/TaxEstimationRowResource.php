<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxEstimationRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $base = (float) $this->total_tax_base;
        $rate = (float) $this->average_tax_rate;
        $amount = (float) $this->total_tax_amount;
        $label = [
            'ppn_keluaran' => 'PPN Keluaran',
            'ppn_masukan' => 'PPN Masukan',
            'pph_final_umkm' => 'PPh Final UMKM',
            'pph' => 'PPh',
            'other' => 'Lainnya',
        ][$this->tax_type] ?? $this->tax_type;
        $count = (int) $this->transaction_count;

        return [
            'tax_type' => $this->tax_type,
            'jenis_pajak' => $label,
            'tax_type_label' => $label,
            'total_tax_base' => $base,
            'total_tax_base_label' => $this->currency($base),
            'dasar_pengenaan_pajak' => $base,
            'dasar_pengenaan_pajak_label' => $this->currency($base),
            'average_tax_rate' => $rate,
            'tarif' => $rate,
            'tarif_label' => rtrim(rtrim(number_format($rate, 4, ',', '.'), '0'), ',').'%',
            'total_tax_amount' => $amount,
            'total_tax_amount_label' => $this->currency($amount),
            'estimasi_pajak' => $amount,
            'estimasi_pajak_label' => $this->currency($amount),
            'transaction_count' => $count,
            'jumlah_transaksi' => $count,
            'keterangan' => 'Estimasi '.$label,
        ];
    }

    protected function currency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
