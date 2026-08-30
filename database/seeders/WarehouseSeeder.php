<?php

namespace Database\Seeders;

use App\Models\Products\Warehouse;
use App\Models\User;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'owner'))
            ->first();

        if (! $owner || ! $owner->store) {
            return;
        }

        Warehouse::query()->firstOrCreate(
            [
                'store_id' => $owner->store->id,
                'warehouse_type' => 'store_stock',
            ],
            [
                'name' => 'Stok Di Toko',
                'code' => 'TOKO-01',
                'address' => 'Alamat Toko Utama',
                'created_by' => $owner->id,
            ]
        );
    }
}
