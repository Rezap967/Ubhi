<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReceivableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $originalAmount = (float) $this->original_amount;
        $paidAmount = (float) $this->paid_amount;
        $outstandingAmount = (float) $this->outstanding_amount;
        $status = $this->effectiveStatus();
        $labels = ['open' => 'Belum Lunas', 'partial' => 'Cicilan', 'paid' => 'Lunas', 'overdue' => 'Jatuh Tempo', 'void' => 'Dibatalkan'];
        $colors = ['open' => 'green', 'partial' => 'blue', 'paid' => 'gray', 'overdue' => 'orange', 'void' => 'gray'];
        $dueDateLabel = $this->due_date?->translatedFormat('d M Y');

        return [
            'id' => $this->id,
            'receivable_no' => $this->receivable_no,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'receivable_date' => $this->receivable_date?->toDateString(),
            'receivable_date_label' => $this->receivable_date?->translatedFormat('d M Y'),
            'due_date' => $this->due_date?->toDateString(),
            'due_date_label' => $dueDateLabel,
            'description' => $this->description,
            'original_amount' => $originalAmount,
            'original_amount_label' => $this->formatCurrency($originalAmount),
            'paid_amount' => $paidAmount,
            'paid_amount_label' => $this->formatCurrency($paidAmount),
            'outstanding_amount' => $outstandingAmount,
            'outstanding_amount_label' => $this->formatCurrency($outstandingAmount),
            'status' => $status,
            'status_label' => $labels[$status] ?? $status,
            'status_color' => $colors[$status] ?? 'gray',
            'journal_entry_id' => $this->journal_entry_id,
            'can_edit' => $status === 'open' && $paidAmount === 0.0,
            'can_delete' => $paidAmount === 0.0 && $this->journal_entry_id === null,
            'can_pay' => in_array($status, ['open', 'partial', 'overdue'], true) && $outstandingAmount > 0,
            'can_void' => $status !== 'void' && $paidAmount === 0.0,
            'payments_count' => $this->whenLoaded('payments', fn (): int => $this->payments->count()),
            'pelanggan' => $this->customer_name,
            'jatuh_tempo' => $dueDateLabel,
            'jatuh_tempo_full' => $this->due_date?->translatedFormat('d F Y'),
            'totalPiutang' => $originalAmount,
            'sudahDiterima' => $paidAmount,
            'sisa' => $outstandingAmount,
            'sisa_label' => $this->formatCurrency($outstandingAmount),
        ];
    }

    protected function effectiveStatus(): string
    {
        if ($this->status === 'void') {
            return 'void';
        }

        $outstandingAmount = (float) $this->outstanding_amount;

        return $outstandingAmount <= 0
            ? 'paid'
            : ($this->due_date?->isBefore(today())
                ? 'overdue'
                : ((float) $this->paid_amount > 0 ? 'partial' : 'open'));
    }

    protected function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
