<?php

namespace App\Services\Konsinyasi;

use App\Models\Konsinyasi\ConsignmentSettlement;
use App\Models\Konsinyasi\ConsignmentSettlementItem;
use App\Models\Konsinyasi\ConsignmentSalesAllocation;
use App\Models\Products\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ConsignmentSettlementService
{
    public function calculate(int $storeId, int $supplierId, string $dateStart, string $dateEnd): array
    {
        $supplier = Supplier::query()
            ->whereKey($supplierId)
            ->where(function ($q) use ($storeId) {
                $q->whereHas('consignmentItems', fn ($i) => $i->where('store_id', $storeId))
                ->orWhereHas('consignmentSalesAllocations', fn ($a) => $a->where('store_id', $storeId));
            })
            ->first();

        if (! $supplier) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Supplier tidak valid atau tidak memiliki transaksi konsinyasi di toko ini.'],
            ]);
        }

        $allocations = ConsignmentSalesAllocation::with(['transaction', 'consignmentItem.product'])
            ->where('store_id', $storeId)
            ->where('supplier_id', $supplierId)
            ->where('settlement_status', 'unsettled')
            ->whereNull('settlement_id')
            ->whereBetween('created_at', [
                Carbon::parse($dateStart, 'Asia/Jakarta')->startOfDay(),
                Carbon::parse($dateEnd, 'Asia/Jakarta')->endOfDay()
            ])
            ->get();

        if ($allocations->isEmpty()) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Tidak ada penjualan konsinyasi unsettled pada periode ini.']
            ]);
        }

        $grossSales = $allocations->sum('gross_amount');
        $supplierPayable = $allocations->sum('supplier_amount');
        $storeMargin = $allocations->sum('store_margin');

        $mappedItems = $allocations->map(fn($alloc) => [
            'sales_allocation_id' => $alloc->id,
            'sale_id' => $alloc->pos_transaction_id,
            'sale_no' => $alloc->transaction?->transaction_number ?? '-',
            'sale_date' => $alloc->created_at->format('Y-m-d H:i:s'),
            'consignment_item_id' => $alloc->consignment_item_id,
            'product_name' => $alloc->consignmentItem?->product?->name ?? 'Produk',
            'product_sku' => $alloc->consignmentItem?->product?->sku ?? '-',
            'quantity_sold' => (float)$alloc->quantity_sold,
            'selling_price' => (float)$alloc->selling_price,
            'gross_amount' => (float)$alloc->gross_amount,
            'supplier_amount' => (float)$alloc->supplier_amount,
            'store_margin' => (float)$alloc->store_margin,
            'settlement_status' => $alloc->settlement_status,
        ]);

        return [
            'supplier_id' => $supplier->id,
            'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'gross_sales' => (float)$grossSales,
            'supplier_payable' => (float)$supplierPayable,
            'store_margin' => (float)$storeMargin,
            'items' => $mappedItems,
        ];
    }

    public function createDraft(int $storeId, int $userId, array $data): ConsignmentSettlement
    {
        if (!empty($data['request_uuid'])) {
            $existing = ConsignmentSettlement::where('store_id', $storeId)
                ->where('request_uuid', $data['request_uuid'])
                ->first();
            if ($existing) return $existing;
        }

        return DB::transaction(function () use ($storeId, $userId, $data) {
            $allocations = ConsignmentSalesAllocation::where('store_id', $storeId)
                ->whereIn('id', $data['allocation_ids'])
                ->where('supplier_id', $data['supplier_id'])
                ->where('settlement_status', 'unsettled')
                ->whereNull('settlement_id')
                ->lockForUpdate()
                ->get();

            if ($allocations->count() !== count($data['allocation_ids'])) {
                throw ValidationException::withMessages([
                    'allocation_ids' => ['Beberapa transaksi penjualan sudah diproses atau tidak valid.']
                ]);
            }

            $grossSales = $allocations->sum('gross_amount');
            $supplierPayable = $allocations->sum('supplier_amount');
            $storeMargin = $allocations->sum('store_margin');

            $dateCode = Carbon::now('Asia/Jakarta')->format('Ymd');
            $lastCode = ConsignmentSettlement::where('store_id', $storeId)
                ->where('settlement_code', 'LIKE', "KPY-{$dateCode}-%")
                ->lockForUpdate()
                ->count();
            $code = sprintf("KPY-%s-%03d", $dateCode, $lastCode + 1);

            $settlement = ConsignmentSettlement::create([
                'store_id' => $storeId,
                'settlement_code' => $code,
                'supplier_id' => $data['supplier_id'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'gross_sales' => $grossSales,
                'supplier_payable' => $supplierPayable,
                'store_margin' => $storeMargin,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_reference_no' => $data['payment_reference_no'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'request_uuid' => $data['request_uuid'] ?? null,
            ]);

            foreach ($allocations as $alloc) {
                ConsignmentSettlementItem::create([
                    'settlement_id' => $settlement->id,
                    'sales_allocation_id' => $alloc->id,
                    'quantity_sold' => $alloc->quantity_sold,
                    'gross_amount' => $alloc->gross_amount,
                    'supplier_amount' => $alloc->supplier_amount,
                    'store_margin' => $alloc->store_margin,
                ]);

                // Tandai allocation dengan settlement_id agar tidak dikalkulasi ganda
                $alloc->update(['settlement_id' => $settlement->id]);
            }

            return $settlement;
        });
    }

    public function approve(int $storeId, int $userId, int $settlementId): ConsignmentSettlement
    {
        return DB::transaction(function () use ($storeId, $userId, $settlementId) {
            $settlement = ConsignmentSettlement::where('store_id', $storeId)
                ->lockForUpdate()
                ->findOrFail($settlementId);

            if ($settlement->status !== 'draft') {
                abort(409, 'Hanya settlement berstatus draft yang dapat disetujui.');
            }

            $now = Carbon::now('Asia/Jakarta');

            $settlement->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => $now,
            ]);

            $allocationIds = $settlement->items()->pluck('sales_allocation_id');

            ConsignmentSalesAllocation::whereIn('id', $allocationIds)
                ->where('settlement_status', 'unsettled')
                ->lockForUpdate()
                ->update([
                    'settlement_status' => 'settled',
                    'settled_at' => $now,
                ]);

            return $settlement;
        });
    }
}