<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->country(),
            'description' => $this->faker->sentence(),
            'price'       => $this->faker->randomFloat(2, 5, 50),
            'is_available' =>true,
            'category_id'=> $this->faker->numberBetween(1, 10),
        ];
    }
}
