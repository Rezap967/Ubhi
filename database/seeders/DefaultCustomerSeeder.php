<?php

namespace Database\Seeders;

use App\Models\Customer\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultCustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            // Owner ada di $store->owner_id
            $createdBy = $store->owner_id ?: 1; // Fallback ke user 1 (superadmin/system)

            Customer::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'code' => 'MBR-000000',
                ],
                [
                    'customer_group_id' => null,
                    'name' => 'UMUM',
                    'phone' => null,
                    'email' => null,
                    'address' => null,
                    'status' => 'aktif',
                    'notes' => 'Pelanggan umum/default POS',
                    'created_by' => $createdBy,
                ]
            );
        }
    }
}
