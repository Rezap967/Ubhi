<?php

namespace Database\Seeders;

use App\Models\Accounting\TaxRate;
use App\Models\Store;
use Illuminate\Database\Seeder;

class DefaultTaxRateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Store::query()->get() as $store) {
            TaxRate::query()->firstOrCreate(['store_id' => $store->id, 'code' => 'PPN11'], ['name' => 'PPN 11%', 'type' => 'ppn', 'rate' => 11, 'is_system' => true, 'is_active' => true]);
            TaxRate::query()->firstOrCreate(['store_id' => $store->id, 'code' => 'PPHFINALUMKM05'], ['name' => 'PPh Final UMKM 0,5%', 'type' => 'pph_final', 'rate' => 0.5, 'is_system' => true, 'is_active' => true]);
        }
    }
}
