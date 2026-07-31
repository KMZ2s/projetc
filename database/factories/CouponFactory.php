<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['percentage', 'fixed']);
        $value = $type === 'percentage' ? fake()->randomFloat(2, 5, 50) : fake()->randomFloat(2, 10, 200);

        return [
            'code' => fake()->unique()->bothify('PROMO??###'),
            'type' => $type,
            'value' => $value,
            'min_order_value' => fake()->optional()->randomFloat(2, 50, 500),
            'usage_limit' => fake()->optional()->numberBetween(1, 100),
            'usage_per_customer' => fake()->optional()->numberBetween(1, 5),
            'used_count' => 0,
            'valid_from' => fake()->optional()->date(),
            'valid_to' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'status' => fake()->randomElement(['active', 'inactive', 'expired']),
        ];
    }
}