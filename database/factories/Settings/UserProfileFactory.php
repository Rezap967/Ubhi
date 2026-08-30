<?php

namespace Database\Factories\Settings;

use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'birth_date' => fake()->date('Y-m-d', '-18 years'),
            'address' => fake()->address(),
            'avatar' => null,
        ];
    }
}
