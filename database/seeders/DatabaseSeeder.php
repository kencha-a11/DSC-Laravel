<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\TimeLog;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesLog;
use App\Models\InventoryLog;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with story-driven data.
     */
    public function run(): void
    {
        // Use Manila timezone consistently
        $timezone = 'Asia/Manila';
        $this->command->info("📖 Starting Story-Driven Database Seeding in {$timezone}...\n");

        // Story timeline: 3 months ago to today
        $storyStartDate = Carbon::now($timezone)->subMonths(3);
        $today = Carbon::now($timezone);

        // 1️⃣ Seed Categories
        $categories = Category::factory(5)->create();
        $this->command->info("✅ Created {$categories->count()} categories");

        // 2️⃣ Seed Users
        $users = collect();
        for ($i = 0; $i < 5; $i++) {
            $hiredDate = $storyStartDate->copy()->addWeeks($i);
            $user = User::factory()->create([
                'created_at' => $hiredDate,
                'updated_at' => $hiredDate,
            ]);
            $users->push($user);
        }
        $this->command->info("✅ Created {$users->count()} users");

        // 3️⃣ Seed Products and attach categories
        $systemUser = $users->first(); // manager/system
        $products = Product::factory(20)
            ->create()
            ->each(function ($product) use ($categories, $storyStartDate, $systemUser, $timezone) {
                // Attach product to 1-3 random categories
                $product->categories()->attach(
                    $categories->random(rand(1, 3))->pluck('id')->toArray()
                );

                // Set creation date in past
                $productCreatedAt = $storyStartDate->copy()->addDays(rand(0, 7));
                $product->created_at = $productCreatedAt;
                $product->updated_at = $productCreatedAt;
                $product->save();

                // Create initial inventory log
                InventoryLog::create([
                    'user_id' => $systemUser->id,
                    'product_id' => $product->id,
                    'action' => 'created',
                    'quantity_change' => $product->stock_quantity,
                    'created_at' => $productCreatedAt,
                    'updated_at' => $productCreatedAt,
                ]);
            });
        $this->command->info("✅ Created {$products->count()} products with categories");

        // 4️⃣ Simulate daily operations (shifts, sales, inventory)
        $this->simulateDailyOperations($users, $products, $storyStartDate, $today, $timezone);
        $this->command->info("✅ Created sales, time logs, sales logs, and inventory logs");

        // 5️⃣ Fix ongoing TimeLogs (ensure one open per user)
        $this->fixOngoingTimeLogs($users, $timezone);
        $this->command->info("✅ Fixed ongoing TimeLogs for users");

        $this->command->info("\n🎉 Story-Driven Seeding Complete!");
    }

    /**
     * Simulate daily business operations.
     */
    protected function simulateDailyOperations($users, $products, $startDate, $endDate, $timezone)
    {
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $isToday = $currentDate->isToday();

            foreach ($users as $user) {
                // Skip if user not hired yet
                if ($currentDate->lt(Carbon::parse($user->created_at)->timezone($timezone))) continue;

                // 70% chance user works today
                if (!fake()->boolean(70)) continue;

                // Determine shift start time (8AM-10AM)
                $shiftStart = $currentDate->copy()
                    ->setTimezone($timezone)
                    ->setTime(fake()->numberBetween(8, 10), fake()->numberBetween(0, 59), fake()->numberBetween(0, 59));

                // Prevent overlapping shifts
                $latestEnd = TimeLog::where('user_id', $user->id)
                    ->whereDate('start_time', $currentDate->toDateString())
                    ->orderByDesc('end_time')
                    ->value('end_time');
                if ($latestEnd && $shiftStart->lt(Carbon::parse($latestEnd)->timezone($timezone))) {
                    $shiftStart = Carbon::parse($latestEnd)->addMinute()->timezone($timezone);
                }

                // Determine shift duration (4-8 hours)
                $shiftEnd = $shiftStart->copy()->addMinutes(fake()->numberBetween(240, 480));
                if ($shiftEnd->gt(Carbon::now($timezone))) {
                    $shiftEnd = $isToday ? Carbon::now($timezone) : $shiftStart->copy()->addMinutes(15);
                    $status = $isToday ? 'logged_in' : 'logged_out';
                } else {
                    $status = 'logged_out';
                }

                if ($shiftEnd->lt($shiftStart)) {
                    $shiftEnd = $shiftStart->copy()->addMinutes(15);
                }

                // Create TimeLog
                TimeLog::create([
                    'user_id' => $user->id,
                    'start_time' => $shiftStart,
                    'end_time' => $shiftEnd,
                    'status' => $status,
                    'duration' => $shiftStart->diffInMinutes($shiftEnd),
                    'created_at' => $shiftStart,
                    'updated_at' => $shiftEnd,
                ]);

                // Create sales during shift
                if ($shiftEnd || fake()->boolean(30)) {
                    $numSales = rand(1, 5); // increased number for variety
                    for ($s = 0; $s < $numSales; $s++) {
                        $maxMinutes = $shiftStart->diffInMinutes($shiftEnd ?: Carbon::now($timezone));
                        if ($maxMinutes <= 0) continue;

                        $saleTime = $shiftStart->copy()->addMinutes(rand(0, $maxMinutes))
                            ->addSeconds(rand(0, 59));
                        if ($saleTime->gt(Carbon::now($timezone))) continue;

                        // Create Sale
                        $sale = Sale::create([
                            'user_id' => $user->id,
                            'total_amount' => 0,
                            'created_at' => $saleTime,
                            'updated_at' => $saleTime,
                        ]);

                        // Create SalesLog (timestamp matches sale)
                        SalesLog::create([
                            'user_id' => $user->id,
                            'sale_id' => $sale->id,
                            'action' => 'created',
                            'created_at' => $saleTime,
                            'updated_at' => $saleTime,
                        ]);

                        // Random sale items (1-15) to match example
                        $numItems = rand(1, 15);
                        $saleTotal = 0;

                        for ($i = 0; $i < $numItems; $i++) {
                            $product = $products->random();
                            $product->refresh();
                            $availableStock = max(0, $product->stock_quantity);
                            if ($availableStock == 0) continue;

                            $quantity = min(fake()->numberBetween(1, 5), $availableStock);
                            $isDeleted = fake()->boolean(20);
                            $subtotal = $quantity * $product->price;
                            $saleTotal += $subtotal;

                            SaleItem::create([
                                'sale_id' => $sale->id,
                                'product_id' => $isDeleted ? null : $product->id,
                                'quantity' => $quantity,
                                'price' => $product->price,
                                'subtotal' => $subtotal,
                                'snapshot_name' => $product->name ?? 'Deleted Product',
                                'snapshot_quantity' => $quantity,
                                'snapshot_price' => $product->price ?? 0,
                                'created_at' => $saleTime,
                                'updated_at' => $saleTime,
                            ]);

                            // Deduct stock
                            if (!$isDeleted) {
                                InventoryLog::create([
                                    'user_id' => $user->id,
                                    'product_id' => $product->id,
                                    'action' => 'deducted',
                                    'quantity_change' => -$quantity,
                                    'snapshot_name' => $product->name, // ✅ Added snapshot_name
                                    'created_at' => $saleTime,
                                    'updated_at' => $saleTime,
                                ]);

                                $newStock = $product->stock_quantity - $quantity;
                                $threshold = $product->low_stock_threshold;
                                $product->update([
                                    'stock_quantity' => max(0, $newStock),
                                    'status' => match (true) {
                                        $newStock <= 0 => 'out of stock',
                                        $newStock <= $threshold => 'low stock',
                                        default => 'stock',
                                    },
                                    'updated_at' => $saleTime,
                                ]);
                            }
                        }

                        if ($saleTotal > 0) {
                            $sale->update(['total_amount' => $saleTotal, 'updated_at' => $saleTime]);
                        } else {
                            $sale->delete();
                        }
                    }
                }
            }

            // Random daily inventory adjustments (15% chance)
            if (fake()->boolean(15)) {
                $operationTime = $currentDate->copy()->setTimezone($timezone)->setTime(7, 0, 0);
                if ($operationTime->lte(Carbon::now($timezone))) {
                    $productsToAffect = $products->random(rand(2, 5));
                    $operatingUser = $users->random();
                    foreach ($productsToAffect as $product) {
                        $action = fake()->randomElement(['restock', 'adjusted', 'update']);
                        $quantityChange = match ($action) {
                            'restock' => fake()->numberBetween(20, 100),
                            'adjusted' => fake()->numberBetween(-10, 10),
                            'update' => fake()->numberBetween(1, 5),
                        };

                        InventoryLog::create([
                            'user_id' => $operatingUser->id,
                            'product_id' => $product->id,
                            'action' => $action,
                            'quantity_change' => $quantityChange,
                            'snapshot_name' => $product->name, // ✅ Added snapshot_name
                            'created_at' => $operationTime,
                            'updated_at' => $operationTime,
                        ]);

                        if ($action === 'restock' || $action === 'adjusted') {
                            $product->increment('stock_quantity', $quantityChange);
                            $newStock = $product->fresh()->stock_quantity;
                            $threshold = $product->low_stock_threshold;
                            $product->update([
                                'status' => match (true) {
                                    $newStock == 0 => 'out of stock',
                                    $newStock <= $threshold => 'low stock',
                                    default => 'stock',
                                },
                                'updated_at' => $operationTime,
                            ]);
                        }
                    }
                }
            }

            $currentDate->addDay();
        }
    }

    /**
     * Ensure each user has at most one open TimeLog.
     */
    protected function fixOngoingTimeLogs($users, $timezone): void
    {
        $users->each(function ($user) use ($timezone) {
            $openLogs = TimeLog::where('user_id', $user->id)
                ->whereNull('end_time')
                ->orderByDesc('start_time')
                ->get();

            if ($openLogs->count() > 1) {
                // Keep latest, close rest
                $openLogs->skip(1)->each(function ($log) use ($timezone) {
                    $endTime = $log->start_time->copy()->addMinutes(rand(15, 480));
                    if ($endTime->gt(Carbon::now($timezone))) $endTime = Carbon::now($timezone);
                    $log->update([
                        'end_time' => $endTime,
                        'status' => 'logged_out',
                        'duration' => $log->start_time->diffInMinutes($endTime),
                        'updated_at' => $endTime,
                    ]);
                });
            }
        });
    }
}
