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
        // 1️⃣ Seed Categories
        $categories = Category::factory()->count(5)->create();

        // 2️⃣ Seed Products and attach categories
        $products = Product::factory()
            ->count(20)
            ->create()
            ->each(function ($product) use ($categories) {
                $product->categories()->attach(
                    $categories->random(rand(1, 3))->pluck('id')->toArray()
                );
            });

        // 3️⃣ Seed Users + Sales + SaleItems
        User::factory()
            ->count(5)
            ->has(
                \App\Models\Sale::factory()
                    ->count(3)
                    ->has(
                        \App\Models\SaleItem::factory()
                            ->count(4)
                            ->state(function () use ($products) {
                                // Pick a random product
                                $product = $products->random();
                                $snapshotQuantity = fake()->numberBetween(1, 5);

                                // Randomly simulate deleted product
                                $productId = fake()->boolean(20) ? null : $product->id;

                                return [
                                    'product_id' => $productId,
                                    'quantity' => $snapshotQuantity,
                                    'price' => $product->price,
                                    'subtotal' => $snapshotQuantity * $product->price,

                                    // Always fill snapshot fields
                                    'snapshot_name' => $product->name ?? 'Deleted Product',
                                    'snapshot_quantity' => $snapshotQuantity,
                                    'snapshot_price' => $product->price ?? 0,
                                ];
                            }),
                        'saleItems'
                    ),
                'sales'
            )
            ->create();

        // 4️⃣ Seed Logs
        $this->call([
            \Database\Seeders\TimeLogSeeder::class,
            \Database\Seeders\SalesLogSeeder::class,
            \Database\Seeders\InventoryLogSeeder::class,
        ]);
    }
}
