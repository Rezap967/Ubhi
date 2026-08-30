<?php

// database/factories/StoreFactory.php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'code' => 'STR-'.Str::random(10),
            'name' => fake()->company(),
            'logo' => null,
            'email' => Str::random(10).'@example.com',
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'postal_code' => fake()->numerify('#####'),
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'business_type' => fake()->randomElement(['retail', 'grocery', 'fnb']),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
