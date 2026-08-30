<?php

namespace App\Policies;

use App\Models\Products\Supplier;
use App\Models\User;

/**
 * RBAC Pemasok:
 *
 * | Method   | Owner | Manajer | Petugas Gudang | Kasir |
 * |----------|-------|---------|-------------|-------|
 * | viewAny  |  ✅   |   ✅    |     ✅      |  ❌   |
 * | view     |  ✅   |   ✅    |     ✅      |  ❌   |
 * | create   |  ✅   |   ✅    |     ❌      |  ❌   |
 * | update   |  ✅   |   ✅    |     ❌      |  ❌   |
 * | delete   |  ✅   |   ✅    |     ❌      |  ❌   |
 *
 * Catatan: Kasir tidak bisa mengakses Pemasok sama sekali.
 *
 * ASUMSI: User memiliki atribut/accessor `role` bernilai salah satu dari
 * 'owner', 'manajer', 'staf_gudang', 'kasir'. Sesuaikan cara pengecekan
 * role di bawah ini jika sistem RBAC proyek menggunakan mekanisme lain
 * (misal Spatie Permission / kolom role_id).
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer', 'petugas_gudang']);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return in_array($user->role->name, ['owner', 'manajer', 'petugas_gudang']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role->name, ['owner', 'manajer']);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return in_array($user->role->name, ['owner', 'manajer']);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return in_array($user->role->name, ['owner', 'manajer']);
    }
}
