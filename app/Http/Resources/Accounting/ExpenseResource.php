<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $date = $this->expense_date?->toDateString();
        $totalAmount = (float) $this->total_amount;
        $amountLabel = $this->formatCurrency($totalAmount);
        $categoryName = $this->category?->name ?? 'Lainnya';
        $dateLabel = $this->expense_date?->format('d/m');

        return [
            'id' => $this->id,
            'expense_no' => $this->expense_no,
            'expense_date' => $date,
            'date' => $date,
            'date_label' => $dateLabel,
            'description' => $this->description,
            'category' => $this->category ? ExpenseCategoryResource::make($this->category) : null,
            'category_id' => $this->category_id,
            'category_name' => $categoryName,
            'amount' => (float) $this->amount,
            'amount_label' => $this->formatCurrency((float) $this->amount),
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => $totalAmount,
            'total_amount_label' => $amountLabel,
            'payment_method' => $this->payment_method,
            'reference_no' => $this->reference_no,
            'notes' => $this->notes,
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'can_edit' => $this->status === 'draft',
            'can_delete' => $this->status === 'draft',
            'can_void' => $this->status === 'posted',
            'can_print' => true,
            'tanggal' => $dateLabel,
            'deskripsi' => $this->description,
            'kategori' => $categoryName,
            'jumlah' => $totalAmount,
            'jumlah_label' => $amountLabel,
        ];
    }

    protected function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
