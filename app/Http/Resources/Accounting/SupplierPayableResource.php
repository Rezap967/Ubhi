<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPayableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $originalAmount = (float) $this->original_amount;
        $paidAmount = (float) $this->paid_amount;
        $outstandingAmount = (float) $this->outstanding_amount;
        $status = $this->effectiveStatus();
        $statusLabels = [
            'open' => 'Belum Lunas', 'partial' => 'Sebagian', 'paid' => 'Lunas',
            'overdue' => 'Jatuh Tempo', 'void' => 'Dibatalkan',
        ];
        $statusColors = [
            'open' => 'red', 'partial' => 'yellow', 'paid' => 'green',
            'overdue' => 'orange', 'void' => 'gray',
        ];
        $dueDateLabel = $this->due_date?->translatedFormat('d M Y');

        return [
            'id' => $this->id,
            'payable_no' => $this->payable_no,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier_name,
            'payable_date' => $this->payable_date?->toDateString(),
            'payable_date_label' => $this->payable_date?->translatedFormat('d M Y'),
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
            'status_label' => $statusLabels[$status] ?? $status,
            'status_color' => $statusColors[$status] ?? 'gray',
            'journal_entry_id' => $this->journal_entry_id,
            'can_edit' => $status === 'open' && $paidAmount === 0.0,
            'can_delete' => $paidAmount === 0.0 && $this->journal_entry_id === null,
            'can_pay' => in_array($status, ['open', 'partial', 'overdue'], true) && $outstandingAmount > 0,
            'can_void' => $status !== 'void' && $paidAmount === 0.0,
            'payments_count' => $this->whenLoaded('payments', fn (): int => $this->payments->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'pemasok' => $this->supplier_name,
            'jatuh_tempo' => $dueDateLabel,
            'sisa' => $outstandingAmount,
            'sisa_label' => $this->formatCurrency($outstandingAmount),
        ];
    }

    protected function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
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
}
