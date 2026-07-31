<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Administrador
        User::create([
            'name' => 'Admin Replicantfy',
            'first_name' => 'Admin',
            'last_name' => 'Replicantfy',
            'email' => 'admin@replicantfy.com',
            'password' => Hash::make('12345678'),
            'status' => 'active',
            'is_admin' => true,
            'accepts_marketing' => false,
        ]);

        // Cliente comum
        User::create([
            'name' => 'João Cliente',
            'first_name' => 'João',
            'last_name' => 'Silva',
            'email' => 'cliente@example.com',
            'password' => Hash::make('12345678'),
            'phone' => '(11) 98888-7777',
            'cpf_cnpj' => '123.456.789-00',
            'status' => 'active',
            'is_admin' => false,
            'accepts_marketing' => true,
        ]);
    }
}