<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $created = $this->faker->dateTimeBetween('-1 years', 'now');

        return [
            'user_id' => \App\Models\User::factory(),
            'total_amount' => $this->faker->randomFloat(2, 50, 1000),
            'created_at' => $created,
            'updated_at' => $this->faker->dateTimeBetween($created, 'now'),
        ];
    }
}
