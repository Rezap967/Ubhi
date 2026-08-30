<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SupportContactSeeder::class,
            ApplicationVersionSeeder::class,
            WarehouseSeeder::class,
            DefaultCustomerSeeder::class,
            DefaultAccountSeeder::class,
            DefaultExpenseCategorySeeder::class,
            DefaultTaxRateSeeder::class,
        ]);
    }
}
