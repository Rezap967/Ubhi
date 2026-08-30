<?php

namespace App\Repositories\Jualan;

use App\Models\Jualan\Promotion;
use App\Models\Jualan\PromotionTarget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PromotionRepository
{
    public function paginate(array $filters, int $storeId): LengthAwarePaginator
    {
        $query = Promotion::query()
            ->where('store_id', $storeId)
            ->with('targets');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }

        return $query->orderByDesc('priority')->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function findForStore(int $id, int $storeId): Promotion
    {
        return Promotion::query()->where('store_id', $storeId)->with('targets')->findOrFail($id);
    }

    public function create(array $data, array $targets): Promotion
    {
        return DB::transaction(function () use ($data, $targets) {
            $promotion = Promotion::query()->create($data);

            if (! empty($targets)) {
                foreach ($targets as $target) {
                    PromotionTarget::query()->create([
                        'promotion_id' => $promotion->id,
                        'target_type' => $target['target_type'],
                        'target_id' => $target['target_id'],
                    ]);
                }
            }

            return $promotion->fresh('targets');
        });
    }

    public function update(Promotion $promotion, array $data, array $targets): Promotion
    {
        return DB::transaction(function () use ($promotion, $data, $targets) {
            $promotion->update($data);

            if ($promotion->scope === 'semua_produk') {
                $promotion->targets()->delete();
            } else {
                $promotion->targets()->delete();

                foreach ($targets as $target) {
                    PromotionTarget::query()->create([
                        'promotion_id' => $promotion->id,
                        'target_type' => $target['target_type'],
                        'target_id' => $target['target_id'],
                    ]);
                }
            }

            return $promotion->fresh('targets');
        });
    }

    public function archive(Promotion $promotion): bool
    {
        $promotion->status = 'diarsipkan';

        return (bool) $promotion->save() && (bool) $promotion->delete();
    }

    public function getActivePromotionsForStore(int $storeId): Collection
    {
        return Promotion::query()
            ->where('store_id', $storeId)
            ->where('status', 'aktif')
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereNull('deleted_at')
            ->with('targets')
            ->get();
    }

    /**
     * Increment usage_count promosi setelah dipakai dalam transaksi checkout.
     * Dipanggil dari CheckoutService::recordPromotionUsage() di dalam DB transaction
     * yang sama dengan penyimpanan transaksi, agar atomik.
     */
    public function incrementUsage(Promotion $promotion): Promotion
    {
        $promotion->increment('usage_count');

        return $promotion->fresh();
    }
}
