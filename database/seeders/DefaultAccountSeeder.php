<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Store;
use Illuminate\Database\Seeder;

class DefaultAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1', 'name' => 'Aset', 'type' => 'asset'],
            ['code' => '1-1', 'name' => 'Aset Lancar', 'type' => 'asset', 'parent' => '1'],
            ['code' => '1-1100', 'name' => 'Kas', 'type' => 'asset', 'parent' => '1-1'],
            ['code' => '1-1200', 'name' => 'Bank', 'type' => 'asset', 'parent' => '1-1'],
            ['code' => '1-1300', 'name' => 'Piutang Usaha', 'type' => 'asset', 'parent' => '1-1'],
            ['code' => '1-1400', 'name' => 'Persediaan Barang', 'type' => 'asset', 'parent' => '1-1'],
            ['code' => '1-1500', 'name' => 'Beban Dibayar di Muka', 'type' => 'asset', 'parent' => '1-1'],
            ['code' => '2', 'name' => 'Kewajiban', 'type' => 'liability'],
            ['code' => '2-1100', 'name' => 'Hutang Usaha', 'type' => 'liability', 'parent' => '2'],
            ['code' => '2-1200', 'name' => 'Hutang Pajak', 'type' => 'liability', 'parent' => '2'],
            ['code' => '3', 'name' => 'Ekuitas', 'type' => 'equity'],
            ['code' => '3-1100', 'name' => 'Modal Pemilik', 'type' => 'equity', 'parent' => '3'],
            ['code' => '3-1200', 'name' => 'Laba Ditahan', 'type' => 'equity', 'parent' => '3'],
            ['code' => '4', 'name' => 'Pendapatan', 'type' => 'revenue'],
            ['code' => '4-1100', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'parent' => '4'],
            ['code' => '5', 'name' => 'Beban', 'type' => 'expense'],
            ['code' => '5-1100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense', 'parent' => '5'],
            ['code' => '5-1200', 'name' => 'Beban Operasional', 'type' => 'expense', 'parent' => '5'],
            ['code' => '5-1300', 'name' => 'Beban Administrasi', 'type' => 'expense', 'parent' => '5'],
            ['code' => '5-1400', 'name' => 'Beban Depresiasi', 'type' => 'expense', 'parent' => '5'],
        ];

        Store::query()->each(function (Store $store) use ($accounts): void {
            $created = [];

            foreach ($accounts as $definition) {
                $parentId = isset($definition['parent'])
                    ? ($created[$definition['parent']] ?? null)?->id
                    : null;

                $account = Account::query()->firstOrCreate(
                    [
                        'store_id' => (int) $store->getKey(),
                        'code' => $definition['code'],
                    ],
                    [
                        'name' => $definition['name'],
                        'type' => $definition['type'],
                        'normal_balance' => in_array($definition['type'], ['asset', 'expense'], true)
                            ? 'debit'
                            : 'credit',
                        'parent_id' => $parentId,
                        'is_system' => true,
                        'is_active' => true,
                        'sort_order' => 0,
                    ],
                );

                $created[$definition['code']] = $account;
            }
        });
    }
}
