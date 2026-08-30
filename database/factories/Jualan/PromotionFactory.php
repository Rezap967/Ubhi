<?php

namespace Database\Factories\Jualan;

use App\Models\Jualan\Promotion;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        $start = now()->toDateString();

        return [
            'store_id' => Store::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['diskon_persen', 'potongan_tetap']),
            'discount_value' => $this->faker->randomFloat(2, 5, 30),
            'max_discount' => 50000,
            'min_purchase' => 50000,
            'min_qty' => 1,
            'start_date' => $start,
            'end_date' => now()->addDays(14)->toDateString(),
            'scope' => 'semua_produk',
            'max_usage' => null,
            'per_customer_limit' => null,
            'usage_count' => 0,
            'is_stackable' => false,
            'priority' => 10,
            'status' => 'aktif',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
