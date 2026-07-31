<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        Store::updateOrCreate(['id' => 1], [
            'name' => 'Empório Cacau',
            'email' => 'contato@emporiocacau.store',
            'phone' => '',
            'address' => '',
            'currency' => 'BRL',
            'currency_symbol' => 'R$',
            'tax_rate' => 0,
            'timezone' => 'America/Sao_Paulo',
            'active_theme' => 'default',
            'logo' => null,
            'favicon' => null,
            'meta_title' => 'Empório Cacau - Página Inicial',
            'meta_description' => 'Chocolates, cremes e baldes para confeitaria com ofertas especiais e entrega para todo o Brasil.',
        ]);
    }
}
