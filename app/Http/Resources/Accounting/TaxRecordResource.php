<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $types = ['ppn_keluaran' => 'PPN Keluaran', 'ppn_masukan' => 'PPN Masukan', 'pph_final_umkm' => 'PPh Final UMKM', 'pph' => 'PPh', 'other' => 'Lainnya'];
        $labels = ['pending' => 'Pending', 'paid' => 'Dibayar', 'cancelled' => 'Dibatalkan'];
        $base = (float) $this->tax_base;
        $amount = (float) $this->tax_amount;

        return ['id' => $this->id, 'transaction_no' => $this->transaction_no, 'tax_date' => $this->tax_date?->toDateString(), 'tax_date_label' => $this->tax_date?->format('d/m/Y'), 'tax_type' => $this->tax_type, 'tax_type_label' => $types[$this->tax_type] ?? $this->tax_type, 'tax_name' => $this->tax_name, 'tax_base' => $base, 'tax_base_label' => $this->currency($base), 'tax_rate' => (float) $this->tax_rate, 'tax_amount' => $amount, 'tax_amount_label' => $this->currency($amount), 'status' => $this->status, 'status_label' => $labels[$this->status] ?? $this->status, 'status_color' => ['pending' => 'yellow', 'paid' => 'green', 'cancelled' => 'red'][$this->status] ?? 'gray', 'source_type' => $this->source_type, 'source_id' => $this->source_id, 'journal_entry_id' => $this->journal_entry_id, 'reference_no' => $this->reference_no, 'notes' => $this->notes, 'can_edit' => $this->status === 'pending' && $this->journal_entry_id === null, 'can_delete' => $this->status === 'pending' && $this->journal_entry_id === null, 'can_pay' => $this->status === 'pending', 'can_cancel' => $this->status !== 'cancelled', 'noTransaksi' => $this->transaction_no, 'no_transaksi' => $this->transaction_no, 'tanggal' => $this->tax_date?->format('d/m/Y'), 'jenisPajak' => $types[$this->tax_type] ?? $this->tax_type, 'jenis_pajak' => $types[$this->tax_type] ?? $this->tax_type, 'dpp' => $base, 'dpp_omzet' => $base, 'dpp_omzet_label' => $this->currency($base), 'nominalPajak' => $amount, 'nominal_pajak' => $amount, 'nominal_pajak_label' => $this->currency($amount)];
    }

    protected function currency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
