<?php

namespace App\Policies\Kasir;

use App\Models\Kasir\PosSession;
use App\Models\User;

class PosSessionPolicy
{
    /**
     * Apakah user boleh melihat sesi ini.
     */
    public function view(User $user, PosSession $session): bool
    {
        $roleName = $user->role?->name ?? '';
        $storeId = $user->getStoreId();

        if (! in_array($roleName, ['kasir', 'owner'], true)) {
            return false;
        }

        return (int) $session->store_id === (int) $storeId;
    }

    /**
     * Apakah user boleh membuka sesi baru.
     */
    public function open(User $user): bool
    {
        $roleName = $user->role?->name ?? '';

        return in_array($roleName, ['kasir', 'owner'], true)
            && $user->getStoreId() !== null;
    }

    /**
     * Apakah user boleh menutup sesi.
     *
     * - Owner boleh tutup sesi siapapun dalam store yang sama.
     * - Kasir hanya boleh tutup sesi miliknya sendiri.
     */
    public function close(User $user, PosSession $session): bool
    {
        $roleName = $user->role?->name ?? '';
        $storeId = $user->getStoreId();

        if (! in_array($roleName, ['kasir', 'owner'], true)) {
            return false;
        }

        if ((int) $session->store_id !== (int) $storeId) {
            return false;
        }

        if ($roleName === 'owner') {
            return true;
        }

        // Kasir: hanya boleh tutup sesi miliknya
        return (int) $session->user_id === (int) $user->id;
    }
}
