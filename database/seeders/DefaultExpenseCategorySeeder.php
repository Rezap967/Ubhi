<?php

namespace Database\Seeders;

use App\Models\Accounting\ExpenseCategory;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pajak', 'color' => 'red'],
            ['name' => 'Listrik', 'color' => 'yellow'],
            ['name' => 'Gaji', 'color' => 'blue'],
            ['name' => 'Sewa', 'color' => 'purple'],
            ['name' => 'Transport', 'color' => 'green'],
            ['name' => 'ATK', 'color' => 'gray'],
            ['name' => 'Kebersihan', 'color' => 'teal'],
            ['name' => 'Internet', 'color' => 'indigo'],
            ['name' => 'Lainnya', 'color' => 'slate'],
        ];

        Store::query()->each(function (Store $store) use ($categories): void {
            foreach ($categories as $sortOrder => $category) {
                ExpenseCategory::query()->firstOrCreate(
                    [
                        'store_id' => $store->getKey(),
                        'slug' => Str::slug($category['name']),
                    ],
                    [
                        'name' => $category['name'],
                        'color' => $category['color'],
                        'is_system' => true,
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ],
                );
            }
        });
    }
}
