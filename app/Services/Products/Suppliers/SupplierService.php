<?php

// app/Services/Products/Suppliers/SupplierService.php

namespace App\Services\Products\Suppliers;

use App\Exceptions\Suppliers\SupplierInUseException;
use App\Models\Products\Supplier;
use App\Services\Products\AuditLogService;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function create(array $data, int $userId): Supplier
    {
        $supplier = Supplier::query()->create([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'aktif',
            'created_by' => $userId,
        ]);

        $this->auditLogService->log('supplier.create', $supplier);

        return $supplier;
    }

    public function update(Supplier $supplier, array $data, int $userId): Supplier
    {
        $old = $supplier->replicate();
        $old->id = $supplier->id; // replicate() menghapus id — kembalikan secara eksplisit agar reference_id di audit log tidak null

        $supplier->fill([
            'code' => $data['code'] ?? $supplier->code,
            'name' => $data['name'],
            'email' => $data['email'] ?? $supplier->email,
            'phone' => $data['phone'] ?? $supplier->phone,
            'address' => $data['address'] ?? $supplier->address,
            'notes' => $data['notes'] ?? $supplier->notes,
            'status' => $data['status'] ?? $supplier->status,
            'updated_by' => $userId,
        ]);
        $supplier->save();

        $this->auditLogService->log('supplier.update', $supplier, $old);

        return $supplier->fresh();
    }

    public function updateStatus(Supplier $supplier, string $status, int $userId): Supplier
    {
        $supplier->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        $this->auditLogService->log('supplier.update_status', $supplier);

        return $supplier->fresh();
    }

    /**
     * @throws SupplierInUseException
     */
    public function delete(Supplier $supplier, int $userId): void
    {
        // TODO(purchase-relation): pengecekan histori PO di-nonaktifkan sementara
        // karena bergantung pada tabel `purchase_orders`/`purchase_items` yang
        // belum tersedia. Kembalikan setelah modul Purchase siap.
        $poCount = 0;
        $piCount = 0;

        // [ACTIVATE WHEN PURCHASE MODULE READY]
        // $poCount = DB::table('purchase_orders')
        //     ->where('supplier_id', $supplier->id)->count();
        // $piCount = DB::table('purchase_items')
        //     ->where('supplier_id', $supplier->id)->count();

        if ($poCount > 0 || $piCount > 0) {
            $this->auditLogService->log('supplier.delete_rejected', $supplier);

            throw new SupplierInUseException(
                'Pemasok tidak dapat dihapus karena memiliki histori transaksi.'
            );
        }

        DB::transaction(function () use ($supplier, $userId): void {
            $supplier->update(['updated_by' => $userId]);
            $supplier->delete();

            $this->auditLogService->log('supplier.delete', $supplier);
        });
    }
}
