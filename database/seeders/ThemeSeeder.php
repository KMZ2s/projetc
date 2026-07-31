<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Theme;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        Theme::create([
            'name' => 'Tema Padrão',
            'directory' => 'default',
            'version' => '1.0.0',
            'author' => 'Replicantfy',
            'is_active' => true,
            'settings_data' => [
                'primary_color' => '#000000',
                'secondary_color' => '#ffffff',
                'font_family' => 'Arial',
            ],
        ]);
    }
}