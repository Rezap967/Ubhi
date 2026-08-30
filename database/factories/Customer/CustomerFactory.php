<?php

namespace Database\Factories\Customer;

use App\Models\Customer\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'code' => 'MBR-'.$this->faker->numerify('######'),
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'address' => $this->faker->address(),
            'status' => 'aktif',
            'created_by' => User::factory(),
        ];
    }
}
