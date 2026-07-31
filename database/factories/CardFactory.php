<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardFactory extends Factory
{
    protected $model = Card::class;

    public function definition(): array
    {
        $month = str_pad($this->faker->numberBetween(1, 12), 2, '0', STR_PAD_LEFT);
        $year  = $this->faker->numberBetween(date('Y'), date('Y') + 3);

        return [
            'user_id'      => User::factory(), // ou use User::inRandomOrder()->first()->id se já tiver usuários
            'number' => preg_replace('/\D/', '', $this->faker->creditCardNumber()),
            'holder_name'  => strtoupper($this->faker->name()),
            'expiry_month' => $month,
            'expiry_year'  => (string) $year,
            'cvv'          => str_pad($this->faker->numberBetween(0, 999), 3, '0', STR_PAD_LEFT),
            'cpf_cnpj'     => $this->faker->numerify('###########'), // 11 dígitos (CPF)
        ];
    }
}