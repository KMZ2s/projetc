<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StoreSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            PaymentGatewaySeeder::class,
            ThemeSeeder::class,
            CardSeeder::class,
        ]);
    }
}