<?php

namespace App\Observers;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

class StoreObserver
{
    public function created(Store $store): void
    {
        $days = [
            ['day_of_week' => 0, 'day_name' => 'Minggu'],
            ['day_of_week' => 1, 'day_name' => 'Senin'],
            ['day_of_week' => 2, 'day_name' => 'Selasa'],
            ['day_of_week' => 3, 'day_name' => 'Rabu'],
            ['day_of_week' => 4, 'day_name' => 'Kamis'],
            ['day_of_week' => 5, 'day_name' => 'Jumat'],
            ['day_of_week' => 6, 'day_name' => 'Sabtu'],
        ];

        $now = now();

        $rows = array_map(function (array $day) use ($store, $now) {
            return [
                'store_id' => $store->id,
                'day_of_week' => $day['day_of_week'],
                'day_name' => $day['day_name'],
                'open_time' => '08:00:00',
                'close_time' => '21:00:00',
                'is_open' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $days);

        DB::table('operating_hours')->insert($rows);
    }
}
