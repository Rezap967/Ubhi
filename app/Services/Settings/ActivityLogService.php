<?php

// app/Services/Settings/ActivityLogService.php

namespace App\Services\Settings;

use App\Models\Settings\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function __construct(
        protected Request $request
    ) {}

    /**
     * Menyimpan satu baris log ke tabel activity_logs.
     *
     * @param array{
     *     store_id: int,
     *     user_id: int,
     *     action: string,
     *     module: string,
     *     description?: string|null,
     *     reference_type?: string|null,
     *     reference_id?: int|null,
     *     ip_address?: string|null,
     *     user_agent?: string|null,
     * } $data
     */
    public function record(array $data): ActivityLog
    {
        $payload = array_merge([
            'description' => null,
            'reference_type' => null,
            'reference_id' => null,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ], $data);

        return ActivityLog::query()->create($payload);
    }

    /**
     * Mengambil daftar log sesuai aturan akses role.
     *
     * @param array{
     *     module?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     per_page?: int,
     * } $filters
     */
    public function index(int $storeId, int $userId, string $role, array $filters = []): LengthAwarePaginator
    {
        $query = ActivityLog::query()->where('store_id', $storeId);

        if ($role !== 'owner') {
            $query->where('user_id', $userId);
        }

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Ambil satu log by id, dibatasi sesuai aturan akses role.
     */
    public function findOne(int $id, int $storeId, int $userId, string $role): ?ActivityLog
    {
        $query = ActivityLog::query()
            ->where('id', $id)
            ->where('store_id', $storeId);

        if ($role !== 'owner') {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }
}
