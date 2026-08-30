<?php

namespace App\Services\Settings;

use App\Models\Settings\PaymentSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentSettingService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function index(int $storeId): Collection
    {
        return PaymentSetting::query()
            ->where('store_id', $storeId)
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function store(array $data, int $storeId): PaymentSetting
    {
        if (! isset($data['sort_order'])) {
            $maxSortOrder = PaymentSetting::query()
                ->where('store_id', $storeId)
                ->max('sort_order');

            $data['sort_order'] = $maxSortOrder === null ? 0 : $maxSortOrder + 1;
        }

        $data['store_id'] = $storeId;
        $data['status'] = $data['status'] ?? 'active';

        $payment = PaymentSetting::query()->create($data);

        $this->activityLogService->record([
            'store_id' => $storeId,
            'user_id' => Auth::id(),
            'action' => 'add_payment_method',
            'module' => 'settings',
            'description' => "Menambahkan metode pembayaran \"{$payment->name}\"",
            'reference_type' => PaymentSetting::class,
            'reference_id' => $payment->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $payment;
    }

    public function show(int $id, int $storeId): PaymentSetting
    {
        $payment = PaymentSetting::query()
            ->where('id', $id)
            ->where('store_id', $storeId)
            ->first();

        if (! $payment) {
            throw new ModelNotFoundException("Metode pembayaran dengan ID {$id} tidak ditemukan.");
        }

        return $payment;
    }

    public function update(array $data, PaymentSetting $payment): PaymentSetting
    {
        $payment->fill($data);
        $payment->save();

        $this->activityLogService->record([
            'store_id' => $payment->store_id,
            'user_id' => Auth::id(),
            'action' => 'update_payment_method',
            'module' => 'settings',
            'description' => "Memperbarui metode pembayaran \"{$payment->name}\"",
            'reference_type' => PaymentSetting::class,
            'reference_id' => $payment->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $payment->fresh();
    }

    public function toggleStatus(PaymentSetting $payment): PaymentSetting
    {
        $newStatus = $payment->status === 'active' ? 'inactive' : 'active';

        $payment->update(['status' => $newStatus]);

        $this->activityLogService->record([
            'store_id' => $payment->store_id,
            'user_id' => Auth::id(),
            'action' => 'toggle_payment_status',
            'module' => 'settings',
            'description' => "Mengubah status metode pembayaran \"{$payment->name}\" menjadi {$newStatus}",
            'reference_type' => PaymentSetting::class,
            'reference_id' => $payment->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $payment->fresh();
    }

    public function destroy(PaymentSetting $payment): void
    {
        $this->activityLogService->record([
            'store_id' => $payment->store_id,
            'user_id' => Auth::id(),
            'action' => 'delete_payment_method',
            'module' => 'settings',
            'description' => "Menghapus metode pembayaran \"{$payment->name}\"",
            'reference_type' => PaymentSetting::class,
            'reference_id' => $payment->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $payment->delete();
    }

    public function reorder(array $payments, int $storeId): void
    {
        DB::transaction(function () use ($payments, $storeId): void {
            foreach ($payments as $item) {
                PaymentSetting::query()
                    ->where('id', $item['id'])
                    ->where('store_id', $storeId) // keamanan — cegah update lintas toko
                    ->update(['sort_order' => $item['sort_order']]);
            }

            $this->activityLogService->record([
                'store_id' => $storeId,
                'user_id' => Auth::id(),
                'action' => 'reorder_payment_methods',
                'module' => 'settings',
                'description' => 'Mengubah urutan tampilan metode pembayaran',
                'reference_type' => PaymentSetting::class,
                'reference_id' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }
}
