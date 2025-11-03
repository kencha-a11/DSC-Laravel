<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use Carbon\Carbon;

class TimeLogFactory extends Factory
{
    public function definition(): array
    {
        // Pick a realistic year between 2023–2025
        $year = $this->faker->numberBetween(2023, 2025);
        $start = $this->faker->dateTimeBetween("{$year}-01-01", "{$year}-12-31");

        // Get or create a random user
        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        // ✅ Always end the session (no null end_time)
        $end = Carbon::instance($start)->addMinutes($this->faker->numberBetween(15, 480));

        return [
            'user_id' => $user->id,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'logged_out', // Always logged_out since ended
            'created_at' => $start,
            'updated_at' => $end,
        ];
    }
}
