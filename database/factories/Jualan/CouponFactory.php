<?php

namespace Database\Factories\Jualan;

use App\Models\Jualan\Coupon;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        // Get or create owner role for created_by user
        $ownerRole = Role::firstOrCreate(['name' => 'owner']);

        return [
            'store_id' => Store::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('???###')),
            'name' => $this->faker->sentence(3),
            'discount_type' => $this->faker->randomElement(['persentase', 'nominal']),
            'discount_value' => $this->faker->numberBetween(1, 50),
            'max_discount' => $this->faker->optional()->numberBetween(10000, 100000),
            'min_purchase' => $this->faker->optional()->numberBetween(50000, 500000),
            'description' => $this->faker->optional()->sentence(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'total_quota' => $this->faker->optional()->numberBetween(100, 1000),
            'used_count' => 0,
            'per_customer_limit' => $this->faker->numberBetween(1, 5),
            'status' => $this->faker->randomElement(['aktif', 'nonaktif', 'berakhir']),
            'created_by' => User::factory()->state(['role_id' => $ownerRole->id]),
            'updated_by' => null,
        ];
    }
}
