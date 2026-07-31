<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

class VariantFactory extends Factory
{
    protected $model = Variant::class;

    public function definition(): array
    {
        $sizes = ['PP', 'P', 'M', 'G', 'GG'];
        $colors = ['Preto', 'Branco', 'Azul', 'Vermelho', 'Verde'];

        return [
            'product_id' => Product::factory(),
            'sku' => fake()->unique()->ean13(),
            'barcode' => fake()->ean13(),
            'price' => fake()->randomFloat(2, 10, 500),
            'compare_at_price' => fake()->optional()->randomFloat(2, 20, 600),
            'cost_price' => fake()->optional()->randomFloat(2, 5, 400),
            'weight' => fake()->randomFloat(2, 0.1, 3),
            'stock_quantity' => fake()->numberBetween(0, 50),
            'image' => fake()->optional()->imageUrl(),
            'options' => [
                'Tamanho' => fake()->randomElement($sizes),
                'Cor' => fake()->randomElement($colors),
            ],
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}