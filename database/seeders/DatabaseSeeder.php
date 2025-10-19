<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1️⃣ Seed Users + Sales + SaleItems
        $users = User::factory()
            ->count(5)
            ->has(
                \App\Models\Sale::factory()
                    ->count(3)
                    ->has(\App\Models\SaleItem::factory()->count(4), 'saleItems'),
                'sales'
            )
            ->create();

        // 2️⃣ Seed Categories
        $categories = Category::factory()->count(5)->create();

        // 3️⃣ Seed Products
        $products = Product::factory()->count(20)->create();

        // 4️⃣ Attach random categories to products (many-to-many)
        foreach ($products as $product) {
            $product->categories()->attach(
                $categories->random(rand(1, 3))->pluck('id')->toArray()
            );
        }

        // 5️⃣ Seed Logs
        $this->call([
            \Database\Seeders\TimeLogSeeder::class,
            \Database\Seeders\SalesLogSeeder::class,
            \Database\Seeders\InventoryLogSeeder::class,
        ]);
    }
}
