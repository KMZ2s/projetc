<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'currency' => 'BRL',
            'currency_symbol' => 'R$',
            'tax_rate' => fake()->randomFloat(2, 0, 25),
            'timezone' => 'America/Sao_Paulo',
            'active_theme' => 'default',
            'logo' => fake()->optional()->imageUrl(),
            'favicon' => fake()->optional()->imageUrl(),
            'meta_title' => fake()->sentence(),
            'meta_description' => fake()->paragraph(),
        ];
    }
}