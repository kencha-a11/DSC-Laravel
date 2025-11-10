<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProductionAccountSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================
        // Admin account
        // ==========================
        if (!User::where('email', 'admin@example.com')->exists()) {
            User::create([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.com',
                'password' => Hash::make('AdminSecure123!'), // Replace with a strong password
                'role' => 'admin',
                'account_status' => 'activated', // ✅ match migration enum
                'phone_number' => '09123456789', // optional
            ]);
        }

        // ==========================
        // Normal user account
        // ==========================
        if (!User::where('email', 'user@example.com')->exists()) {
            User::create([
                'first_name' => 'Regular',
                'last_name' => 'User',
                'email' => 'user@example.com',
                'password' => Hash::make('UserSecure123!'), // Replace with a strong password
                'role' => 'user',
                'account_status' => 'activated', // ✅ match migration enum
                'phone_number' => '09987654321', // optional
            ]);
        }
    }
}
