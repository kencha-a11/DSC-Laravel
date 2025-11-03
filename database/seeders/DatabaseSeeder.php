<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\TimeLog;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1️⃣ Categories
        $categories = Category::factory(5)->create();

        // 2️⃣ Products with random category attachments
        $products = Product::factory(20)
            ->create()
            ->each(fn($product) => 
                $product->categories()->attach(
                    $categories->random(rand(1, 3))->pluck('id')->toArray()
                )
            );

        // 3️⃣ Users with nested Sales → SaleItems (including deleted product simulation)
        $users = User::factory(5)
            ->has(
                \App\Models\Sale::factory(3)
                    ->has(
                        \App\Models\SaleItem::factory(4)->state(function () use ($products) {
                            $product = $products->random();
                            $snapshotQuantity = fake()->numberBetween(1, 5);
                            $isDeleted = fake()->boolean(20); // 20% chance product deleted

                            return [
                                'product_id' => $isDeleted ? null : $product->id,
                                'quantity' => $snapshotQuantity,
                                'price' => $product->price,
                                'subtotal' => $snapshotQuantity * $product->price,
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

        // 4️⃣ Time, Sales, and Inventory Logs
        $this->call([
            TimeLogSeeder::class,
            SalesLogSeeder::class,
            InventoryLogSeeder::class,
        ]);

        // 5️⃣ Enforce "one open TimeLog per user"
        $this->fixOngoingTimeLogs($users);
    }

    /**
     * Ensure each user has at most one open (unclosed) TimeLog.
     */
    protected function fixOngoingTimeLogs($users): void
    {
        $users->each(function ($user) {
            $openLogs = TimeLog::where('user_id', $user->id)
                ->whereNull('end_time')
                ->orderByDesc('start_time')
                ->get();

            if ($openLogs->count() > 1) {
                // Keep the latest open log, close the rest
                $openLogs->skip(1)->each(function ($log) {
                    $log->update([
                        'end_time' => $log->start_time->copy()->addMinutes(rand(15, 480)),
                        'status' => 'logged_out',
                        'updated_at' => now(),
                    ]);
                });
            }
        });
    }
}
