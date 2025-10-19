<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeLog>
 */
class TimeLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
       // Pick a start time within the last 30 days
        $start = $this->faker->dateTimeBetween('-30 days', 'now');

        // 70% chance to have an end_time (meaning user logged out)
        $hasEnded = $this->faker->boolean(70);

        // If ended, generate end_time between 15 min to 8 hrs after start_time
        $end = $hasEnded
            ? $this->faker->dateTimeBetween(
                $start->format('Y-m-d H:i:s'),
                (clone $start)->modify('+8 hours')
            )
            : null;

        // Get a random user safely (fallback if users table empty)
        $user = User::inRandomOrder()->first();
        if (!$user) {
            $user = User::factory()->create();
        }

        return [
            'user_id' => $user->id,
            'start_time' => $start,
            'end_time' => $end,
            'status' => $end ? 'logged_out' : 'logged_in',
            'created_at' => $start,
            'updated_at' => $end ?? $start,
        ];
    }
}
