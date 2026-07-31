<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Seeder;

class CardSeeder extends Seeder
{
    public function run(): void
    {
        // Garante que haja pelo menos alguns usuários para associar
        if (User::count() === 0) {
            User::factory(5)->create();
        }

        // Cria 50 cartões para teste
        Card::factory(50)->create([
            'user_id' => User::inRandomOrder()->first()->id, // associa a um usuário aleatório (ou use uma closure)
        ]);

        // Ou usando a factory com criação de usuário automático (se preferir)
        // Card::factory(50)->create(); // a factory já cria um usuário novo se usar User::factory()
    }
}