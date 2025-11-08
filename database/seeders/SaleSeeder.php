<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch all users
        $users = User::all();

        foreach ($users as $user) {
            // Create 1–3 sales per user
            $sales = Sale::factory(rand(1, 3))
                ->for($user, 'user')
                ->create();

            foreach ($sales as $sale) {
                // Create 1–4 sale items per sale
                $items = SaleItem::factory(rand(1, 4))
                    ->for($sale, 'sale')
                    ->create();

                // Update total_amount to match sum of SaleItems
                $sale->update([
                    'total_amount' => $items->sum('subtotal'),
                ]);
            }
        }
    }
}
