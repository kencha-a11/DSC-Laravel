<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Sale;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesLog>
 */
class SalesLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = ['created', 'updated', 'deleted'];

        return [
            'user_id' => User::inRandomOrder()->first()->id,
            'sale_id' => Sale::inRandomOrder()->first()->id,
            'action' => $this->faker->randomElement($actions),
        ];
    }
}
