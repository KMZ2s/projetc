<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Kits Confeitaria', 'slug' => 'kits-confeitaria', 'description' => 'Kits e combinações especiais para confeitaria.', 'order' => 1],
            ['name' => 'Baldes Confeitaria', 'slug' => 'baldes-confeitaria', 'description' => 'Cremes e recheios profissionais em grandes formatos.', 'order' => 2],
            ['name' => 'Chocolates Importados', 'slug' => 'chocolates-importados', 'description' => 'Seleção de chocolates importados.', 'order' => 3],
            ['name' => 'Queridinhos', 'slug' => 'queridinhos', 'description' => 'Os favoritos dos nossos clientes.', 'order' => 4],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category + [
                'status' => 'active',
                'show_in_menu' => true,
                'parent_id' => null,
            ]);
        }
    }
}
