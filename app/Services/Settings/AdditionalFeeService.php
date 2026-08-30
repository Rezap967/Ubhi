<?php

namespace App\Services\Settings;

use App\Models\Settings\AdditionalFeeSetting;
use App\Repositories\Settings\Contracts\AdditionalFeeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdditionalFeeService
{
    public function __construct(
        protected AdditionalFeeRepositoryInterface $repository
    ) {}

    public function getAll(int $storeId, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginateByStore($storeId, $filters);
    }

    public function getDetail(int $storeId, int $id): AdditionalFeeSetting
    {
        $fee = $this->repository->findByStore($storeId, $id);

        if (!$fee) {
            throw new ModelNotFoundException("Biaya tambahan tidak ditemukan.");
        }

        return $fee;
    }

    public function create(int $actorId, int $storeId, array $data, Request $request): AdditionalFeeSetting
    {
        return DB::transaction(function () use ($actorId, $storeId, $data, $request) {
            $payload = array_merge($data, [
                'store_id' => $storeId,
                'status' => $data['status'] ?? AdditionalFeeSetting::STATUS_ACTIVE,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $fee = $this->repository->create($payload);

            $this->logActivity(
                storeId: $storeId,
                userId: $actorId,
                action: 'additional_fee.create',
                description: "Menambahkan biaya tambahan {$fee->name}",
                referenceId: $fee->id,
                request: $request
            );

            return $fee;
        });
    }

    public function update(int $actorId, int $storeId, int $id, array $data, Request $request): AdditionalFeeSetting
    {
        return DB::transaction(function () use ($actorId, $storeId, $id, $data, $request) {
            $fee = $this->getDetail($storeId, $id);

            $updatedFee = $this->repository->update($fee, $data);

            $this->logActivity(
                storeId: $storeId,
                userId: $actorId,
                action: 'additional_fee.update',
                description: "Mengubah biaya tambahan {$updatedFee->name}",
                referenceId: $updatedFee->id,
                request: $request
            );

            return $updatedFee;
        });
    }

    public function delete(int $actorId, int $storeId, int $id, Request $request): bool
    {
        return DB::transaction(function () use ($actorId, $storeId, $id, $request) {
            $fee = $this->getDetail($storeId, $id);
            $feeName = $fee->name;
            $feeId = $fee->id;

            $deleted = $this->repository->delete($fee);

            if ($deleted) {
                $this->logActivity(
                    storeId: $storeId,
                    userId: $actorId,
                    action: 'additional_fee.delete',
                    description: "Menghapus biaya tambahan {$feeName}",
                    referenceId: $feeId,
                    request: $request
                );
            }

            return $deleted;
        });
    }

    /**
     * Insert Activity Log
     */
    private function logActivity(
        int $storeId,
        int $userId,
        string $action,
        string $description,
        int $referenceId,
        Request $request
    ): void {
        DB::table('activity_logs')->insert([
            'store_id' => $storeId,
            'user_id' => $userId,
            'module' => 'settings',
            'action' => $action,
            'description' => $description,
            'reference_type' => AdditionalFeeSetting::class,
            'reference_id' => $referenceId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}