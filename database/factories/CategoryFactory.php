<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'description' => $this->faker->sentence,
            'is_active' => $this->faker->boolean,
            'sort_order' => $this->faker->numberBetween(0, 10),
            'created_at' => $this->faker->dateTime,
            'updated_at' => $this->faker->dateTime, 
        ];
    }
}
