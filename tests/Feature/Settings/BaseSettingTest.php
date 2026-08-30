<?php

namespace Tests\Feature\Settings;

use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class BaseSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Store $store;

    protected User $kasir;

    protected User $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    /**
     * Pastikan 3 role dasar selalu tersedia di setiap test.
     */
    protected function seedRoles(): void
    {
        foreach (['owner', 'kasir', 'petugas_gudang'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
    }

    /**
     * Buat user Owner lengkap dengan Store miliknya + profil.
     */
    protected function createOwner(): User
    {
        $role = Role::where('name', 'owner')->firstOrFail();

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $store = Store::factory()->create([
            'owner_id' => $user->id,
        ]);

        UserProfile::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        $this->owner = $user;
        $this->store = $store;

        return $user;
    }

    /**
     * Buat user Kasir, terhubung ke store yang sudah dibuat Owner.
     * Jika Owner/Store belum ada, buat dulu otomatis.
     */
    protected function createKasir(): User
    {
        if (! isset($this->store)) {
            $this->createOwner();
        }

        $role = Role::where('name', 'kasir')->firstOrFail();

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        UserProfile::factory()->create([
            'user_id' => $user->id,
            'store_id' => $this->store->id,
        ]);

        $this->kasir = $user;

        return $user;
    }

    /**
     * Buat user Petugas Gudang, terhubung ke store yang sama.
     */
    protected function createGudang(): User
    {
        if (! isset($this->store)) {
            $this->createOwner();
        }

        $role = Role::where('name', 'petugas_gudang')->firstOrFail();

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        UserProfile::factory()->create([
            'user_id' => $user->id,
            'store_id' => $this->store->id,
        ]);

        $this->gudang = $user;

        return $user;
    }

    protected function actingAsOwner(): User
    {
        if (! isset($this->owner)) {
            $this->createOwner();
        }

        Sanctum::actingAs($this->owner);

        return $this->owner;
    }

    protected function actingAsKasir(): User
    {
        if (! isset($this->kasir)) {
            $this->createKasir();
        }

        Sanctum::actingAs($this->kasir);

        return $this->kasir;
    }

    protected function actingAsGudang(): User
    {
        if (! isset($this->gudang)) {
            $this->createGudang();
        }

        Sanctum::actingAs($this->gudang);

        return $this->gudang;
    }
}
