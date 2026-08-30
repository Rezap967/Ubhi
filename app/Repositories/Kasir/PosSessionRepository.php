<?php

namespace App\Repositories\Kasir;

use App\Models\Kasir\PosSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PosSessionRepository
{
    /**
     * Cari sesi open berdasarkan store, user, dan device.
     */
    public function findOpenSession(int $storeId, int $userId, string $deviceId): ?PosSession
    {
        return PosSession::query()
            ->where('store_id', $storeId)
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('status', 'open')
            ->first();
    }

    /**
     * Cek apakah user sudah punya sesi open di store + device ini.
     */
    public function hasOpenSession(int $storeId, int $userId, string $deviceId): bool
    {
        return PosSession::query()
            ->where('store_id', $storeId)
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('status', 'open')
            ->exists();
    }

    /**
     * Buat sesi baru.
     */
    public function create(array $data): PosSession
    {
        return PosSession::create($data);
    }

    /**
     * Lock session for update (pesimistic lock).
     *
     * @throws ModelNotFoundException
     */
    public function lockById(int $id): PosSession
    {
        return PosSession::query()
            ->lockForUpdate()
            ->findOrFail($id);
    }
}
