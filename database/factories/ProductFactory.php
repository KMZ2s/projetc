<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        return [
            'category_id' => Category::inRandomOrder()->first()?->id,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraphs(3, true),
            'short_description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'compare_at_price' => fake()->optional()->randomFloat(2, 20, 1200),
            'cost_price' => fake()->optional()->randomFloat(2, 5, 800),
            'sku' => fake()->unique()->ean13(),
            'barcode' => fake()->ean13(),
            'weight' => fake()->randomFloat(2, 0.1, 5),
            'width' => fake()->randomFloat(2, 5, 50),
            'height' => fake()->randomFloat(2, 5, 50),
            'depth' => fake()->randomFloat(2, 5, 50),
            'status' => fake()->randomElement(['draft', 'active', 'inactive']),
            'featured' => fake()->boolean(20),
            'stock_quantity' => fake()->numberBetween(0, 100),
        ];
    }

    public function withVariants(int $count = 2): static
    {
        return $this->has(VariantFactory::new()->count($count), 'variants');
    }
}
