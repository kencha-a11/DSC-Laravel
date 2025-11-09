<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use Carbon\Carbon;

class TimeLogFactory extends Factory
{
    public function definition(): array
    {
        $timezone = 'Asia/Manila';
        $year = $this->faker->numberBetween(2023, 2025);

        // Start time in Manila timezone
        $start = Carbon::instance(
            $this->faker->dateTimeBetween("{$year}-01-01", "{$year}-12-31")
        )->setTimezone($timezone);

        // Random user
        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        // End time (shift duration 15–480 mins)
        $end = $start->copy()->addMinutes($this->faker->numberBetween(15, 480));

        // Duration in minutes (Manila timezone)
        $duration = $start->diffInMinutes($end);

        return [
            'user_id' => $user->id,
            'start_time' => $start->copy()->setTimezone('UTC'),
            'end_time' => $end->copy()->setTimezone('UTC'),
            'status' => 'logged_out',
            'duration' => $duration,
            'created_at' => $start->copy()->setTimezone('UTC'),
            'updated_at' => $end->copy()->setTimezone('UTC'),
        ];
    }
}
