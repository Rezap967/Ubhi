<?php

namespace App\Services\Accounting;

use App\Models\Accounting\RecurringExpense;
use App\Models\Accounting\RecurringExpenseGeneration;
use App\Repositories\Accounting\RecurringExpenseRepository;
// Reuse Service Pengeluaran Existing
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecurringExpenseService
{
    public function __construct(
        protected RecurringExpenseRepository $repository,
        protected ExpenseService $expenseService
    ) {}

    public function create(int $storeId, int $userId, array $data): RecurringExpense
    {
        return DB::transaction(function () use ($storeId, $userId, $data) {
            $todayStr = now()->format('Ymd');

            // Auto lock & generate code REC-YYYYMMDD-XXXX
            $lastRecord = RecurringExpense::where('store_id', $storeId)
                ->where('recurring_code', 'like', "REC-{$todayStr}-%")
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $sequence = 1;
            if ($lastRecord && preg_match('/REC-\d{8}-(\d{4})/', $lastRecord->recurring_code, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            $code = sprintf('REC-%s-%04d', $todayStr, $sequence);

            return RecurringExpense::create([
                'store_id' => $storeId,
                'recurring_code' => $code,
                'name' => $data['name'],
                'expense_category_id' => $data['expense_category_id'],
                'amount' => $data['amount'],
                'frequency' => $data['frequency'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
                'next_due_date' => $data['start_date'],
                'created_by' => $userId,
            ]);
        });
    }

    public function update(RecurringExpense $expense, array $data): RecurringExpense
    {
        $expense->update([
            'name' => $data['name'],
            'expense_category_id' => $data['expense_category_id'],
            'amount' => $data['amount'],
            'frequency' => $data['frequency'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'payment_method' => $data['payment_method'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $expense;
    }

    public function updateStatus(RecurringExpense $expense, string $status): RecurringExpense
    {
        $expense->update(['status' => $status]);

        return $expense;
    }

    public function generate(int $id, int $storeId, int $userId): array
    {
        return DB::transaction(function () use ($id, $storeId, $userId) {
            $template = RecurringExpense::where('store_id', $storeId)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($template->status === 'paused') {
                throw new HttpException(409, 'Biaya berulang sedang dijeda.');
            }

            $today = now()->startOfDay();
            if ($template->end_date && Carbon::parse($template->end_date)->startOfDay()->lt($today)) {
                throw new HttpException(409, 'Jadwal biaya berulang sudah berakhir.');
            }

            // Hitung Period Key
            $targetDate = Carbon::parse($template->next_due_date);
            $periodKey = match ($template->frequency) {
                'daily' => $targetDate->format('Y-m-d'),
                'weekly' => $targetDate->format('o-\WW'),
                'monthly' => $targetDate->format('Y-m'),
                'yearly' => $targetDate->format('Y'),
            };

            // Idempotency Check
            $existingGeneration = RecurringExpenseGeneration::where('recurring_expense_id', $template->id)
                ->where('period_key', $periodKey)
                ->first();

            if ($existingGeneration) {
                return [
                    'generated' => false,
                    'expense_id' => $existingGeneration->expense_id,
                    'message' => 'Pengeluaran untuk periode ini sudah pernah dibuat sebelumnya.',
                ];
            }

            // Panggil Service Pengeluaran Existing (Reuse Expense & Journal Creation)
            $expenseData = [
                'store_id' => $storeId,
                'expense_category_id' => $template->expense_category_id,
                'amount' => $template->amount,
                'payment_method' => $template->payment_method,
                'date' => $targetDate->format('Y-m-d'),
                'description' => "[Biaya Berulang] {$template->name} ({$periodKey})",
                'notes' => $template->notes,
                'created_by' => $userId,
            ];

            // service reuse akan throw Exception 422 jika mapping COA / periode terkunci
            $createdExpense = $this->expenseService->createExpenseAndJournal($expenseData);

            // Simpan log Generation
            RecurringExpenseGeneration::create([
                'store_id' => $storeId,
                'recurring_expense_id' => $template->id,
                'period_key' => $periodKey,
                'expense_id' => $createdExpense->id,
                'generated_at' => now(),
                'generated_by' => $userId,
            ]);

            // Advance Next Due Date
            $nextDueDate = match ($template->frequency) {
                'daily' => $targetDate->addDay(),
                'weekly' => $targetDate->addWeek(),
                'monthly' => $targetDate->addMonth(),
                'yearly' => $targetDate->addYear(),
            };

            $template->update([
                'next_due_date' => $nextDueDate->format('Y-m-d'),
                'last_generated_at' => now(),
            ]);

            return [
                'generated' => true,
                'expense_id' => $createdExpense->id,
                'message' => 'Pengeluaran aktual berhasil dibuat.',
            ];
        });
    }
}
