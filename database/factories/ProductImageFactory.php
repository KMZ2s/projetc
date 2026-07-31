<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_id' => null,
            'src' => fake()->imageUrl(640, 480, 'products', true),
            'alt' => fake()->sentence(),
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_id' => $variantId,
        ]);
    }
}