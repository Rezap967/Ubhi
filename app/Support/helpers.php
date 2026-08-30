<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (! function_exists('active_store_id')) {
    function active_store_id(): int
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 401, 'Anda harus login terlebih dahulu.');

        $storeId = $user->getStoreId();

        abort_unless($storeId !== null && $storeId > 0, 403, 'Toko aktif belum dipilih.');

        return (int) $storeId;
    }
}
