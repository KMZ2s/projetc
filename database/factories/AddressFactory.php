<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'address_type' => fake()->randomElement(['billing', 'shipping', 'both']),
            'zipcode' => fake()->postcode(),
            'street' => fake()->streetName(),
            'number' => fake()->buildingNumber(),
            'complement' => fake()->optional()->secondaryAddress(),
            'neighborhood' => fake()->word(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'country' => 'BR',
            'is_default' => fake()->boolean(30),
        ];
    }
}