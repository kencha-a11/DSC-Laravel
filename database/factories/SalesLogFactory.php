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
            'user_id' => \App\Models\User::factory(),
            'sale_id' => \App\Models\Sale::factory(),
            'action' => $this->faker->randomElement($actions),
            'device_datetime' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'device_timezone' => $this->faker->randomElement(['Asia/Manila', 'UTC', 'America/New_York']),
        ];
    }
}
