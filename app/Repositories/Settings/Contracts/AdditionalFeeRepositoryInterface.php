<?php

namespace App\Repositories\Settings\Contracts;

use App\Models\Settings\AdditionalFeeSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdditionalFeeRepositoryInterface
{
    /**
     * Paginate data per store_id dengan filter
     */
    public function paginateByStore(int $storeId, array $filters = []): LengthAwarePaginator;

    /**
     * Cari detail record berdasarkan store_id dan id
     */
    public function findByStore(int $storeId, int $id): ?AdditionalFeeSetting;

    /**
     * Simpan record baru
     */
    public function create(array $data): AdditionalFeeSetting;

    /**
     * Update record
     */
    public function update(AdditionalFeeSetting $fee, array $data): AdditionalFeeSetting;

    /**
     * Hapus record
     */
    public function delete(AdditionalFeeSetting $fee): bool;

    /**
     * Cek keberadaan nama pada store_id
     */
    public function existsNameInStore(int $storeId, string $name, ?int $ignoreId = null): bool;
}