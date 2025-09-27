<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // general factory
        \App\Models\User::factory()->count(5)->create();

        // single specific user
        // \App\Models\User::factory()->create();
    }
}
