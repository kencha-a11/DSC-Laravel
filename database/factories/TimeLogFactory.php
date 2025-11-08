<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use Carbon\Carbon;

class TimeLogFactory extends Factory
{

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2023, 2025);
        $start = $this->faker->dateTimeBetween("{$year}-01-01", "{$year}-12-31");

        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        $end = Carbon::instance($start)->addMinutes($this->faker->numberBetween(15, 480));

        $duration = $end->diffInMinutes($start); // Calculate duration in minutes

        return [
            'user_id' => $user->id,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'logged_out',
            'duration' => $duration,
            'created_at' => $start,
            'updated_at' => $end,
        ];
    }
}
