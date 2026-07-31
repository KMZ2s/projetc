<?php

namespace Database\Factories;

use App\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    public function definition(): array
    {
        $directory = fake()->unique()->word();
        return [
            'name' => ucfirst($directory),
            'directory' => $directory,
            'version' => fake()->semver(),
            'author' => fake()->name(),
            'is_active' => false,
            'settings_data' => [
                'primary_color' => fake()->hexColor(),
                'secondary_color' => fake()->hexColor(),
                'font_family' => fake()->randomElement(['Arial', 'Helvetica', 'Roboto']),
            ],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}