<?php

namespace Database\Factories;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->word(),
            'slug'                => fake()->unique()->slug(),
            'is_active'           => fake()->boolean(30),
            'api_key'             => fake()->optional()->uuid(),
            'additional_settings' => [
                'api_base_url' => 'https://api.blackcatpay.com.br/api',
            ],
            'position'            => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Estado: gateway ativo e configurado (útil em testes do checkout).
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'api_key'   => fake()->uuid(),
        ]);
    }

    /**
     * Estado: BlackcatPay ativo (single-gateway por instância).
     */
    public function blackcatpay(): static
    {
        return $this->state(fn () => [
            'name'                => 'BlackcatPay',
            'slug'                => 'blackcatpay',
            'is_active'           => true,
            'api_key'             => fake()->uuid(),
            'additional_settings' => [
                'api_base_url' => 'https://api.blackcatpay.com.br/api',
            ],
            'position'            => 0,
        ]);
    }
}