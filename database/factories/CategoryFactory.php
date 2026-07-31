<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();
        return [
            'parent_id' => null,
            'name' => ucfirst($name),
            'slug' => $name,
            'description' => fake()->sentence(),
            'image' => fake()->optional()->imageUrl(),
            'status' => fake()->randomElement(['active', 'inactive']),
            'order' => fake()->numberBetween(0, 100),
        ];
    }

    public function withParent(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }
}