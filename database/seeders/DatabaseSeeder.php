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
    public function run(): void
    {
        $timezone = 'Asia/Manila';
        $this->command->info("📖 Starting Story-Driven Database Seeding in {$timezone}...\n");

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
        $systemUser = $users->first();
        $products = Product::factory(20)
            ->create()
            ->each(function ($product) use ($categories, $storyStartDate, $systemUser) {
                $product->categories()->attach(
                    $categories->random(rand(1, 3))->pluck('id')->toArray()
                );

                $productCreatedAt = $storyStartDate->copy()->addDays(rand(0, 7));
                $product->update([
                    'created_at' => $productCreatedAt,
                    'updated_at' => $productCreatedAt,
                ]);

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

        // 4️⃣ Simulate daily operations
        $this->simulateDailyOperations($users, $products, $storyStartDate, $today, $timezone);
        $this->command->info("✅ Created sales, time logs, and inventory logs");

        // 5️⃣ Fix ongoing logs
        $this->fixOngoingTimeLogs($users, $timezone);
        $this->command->info("✅ Fixed ongoing TimeLogs for users");

        $this->command->info("\n🎉 Story-Driven Seeding Complete!");
    }

    protected function simulateDailyOperations($users, $products, $startDate, $endDate, $timezone)
    {
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $isToday = $currentDate->isToday();

            foreach ($users as $user) {
                if ($currentDate->lt(Carbon::parse($user->created_at)->timezone($timezone))) continue;
                if (!fake()->boolean(70)) continue;

                $hasTodayLog = TimeLog::where('user_id', $user->id)
                    ->whereDate('start_time', $currentDate->toDateString())
                    ->exists();
                if ($hasTodayLog) continue;

                $shiftStart = $currentDate->copy()
                    ->setTimezone($timezone)
                    ->setTime(fake()->numberBetween(8, 10), fake()->numberBetween(0, 59), fake()->numberBetween(0, 59));

                $shiftEnd = $shiftStart->copy()->addMinutes(fake()->numberBetween(240, 480));

                if ($shiftEnd->gt(Carbon::now($timezone)) && $isToday) {
                    $status = 'logged_in';
                    $shiftEndForDB = null;
                    $duration = null;
                } else {
                    $status = 'logged_out';
                    $shiftEndForDB = $shiftEnd->copy();
                    $duration = $shiftStart->diffInMinutes($shiftEnd);
                }

                TimeLog::create([
                    'user_id' => $user->id,
                    'start_time' => $shiftStart->copy()->setTimezone('UTC'),
                    'end_time' => $shiftEndForDB?->copy()->setTimezone('UTC'),
                    'status' => $status,
                    'duration' => $duration,
                    'created_at' => $shiftStart->copy()->setTimezone('UTC'),
                    'updated_at' => ($shiftEndForDB ?? $shiftStart)->copy()->setTimezone('UTC'),
                ]);

                // Create sales
                if ($shiftEnd || fake()->boolean(30)) {
                    $numSales = rand(1, 5);
                    for ($s = 0; $s < $numSales; $s++) {
                        $maxMinutes = $shiftEnd ? $shiftStart->diffInMinutes($shiftEnd) : 300;
                        if ($maxMinutes <= 0) continue;

                        $saleTime = $shiftStart->copy()->addMinutes(rand(0, $maxMinutes))->addSeconds(rand(0, 59));
                        if ($saleTime->gt(Carbon::now($timezone))) continue;

                        $sale = Sale::create([
                            'user_id' => $user->id,
                            'total_amount' => 0,
                            'created_at' => $saleTime->copy()->setTimezone('UTC'),
                            'updated_at' => $saleTime->copy()->setTimezone('UTC'),
                        ]);

                        SalesLog::create([
                            'user_id' => $user->id,
                            'sale_id' => $sale->id,
                            'action' => 'created',
                            'created_at' => $saleTime->copy()->setTimezone('UTC'),
                            'updated_at' => $saleTime->copy()->setTimezone('UTC'),
                        ]);

                        $numItems = rand(1, 15);
                        $saleTotal = 0;

                        for ($i = 0; $i < $numItems; $i++) {
                            $product = $products->random()->refresh();
                            if ($product->stock_quantity <= 0) continue;

                            $quantity = min(fake()->numberBetween(1, 5), $product->stock_quantity);
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
                                'created_at' => $saleTime->copy()->setTimezone('UTC'),
                                'updated_at' => $saleTime->copy()->setTimezone('UTC'),
                            ]);

                            if (!$isDeleted) {
                                $deductQty = min($quantity, $product->stock_quantity);

                                InventoryLog::create([
                                    'user_id' => $user->id,
                                    'product_id' => $product->id,
                                    'action' => 'deducted',
                                    'quantity_change' => -$deductQty,
                                    'snapshot_name' => $product->name,
                                    'created_at' => $saleTime->copy()->setTimezone('UTC'),
                                    'updated_at' => $saleTime->copy()->setTimezone('UTC'),
                                ]);

                                $newStock = max(0, $product->stock_quantity - $deductQty);
                                $product->update([
                                    'stock_quantity' => $newStock,
                                    'status' => match (true) {
                                        $newStock <= 0 => 'out of stock',
                                        $newStock <= $product->low_stock_threshold => 'low stock',
                                        default => 'stock',
                                    },
                                    'updated_at' => $saleTime->copy()->setTimezone('UTC'),
                                ]);
                            }
                        }

                        if ($saleTotal > 0) {
                            $sale->update(['total_amount' => $saleTotal]);
                        } else {
                            $sale->delete();
                        }
                    }
                }
            }

            // Random inventory adjustments
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
                            'snapshot_name' => $product->name,
                            'created_at' => $operationTime->copy()->setTimezone('UTC'),
                            'updated_at' => $operationTime->copy()->setTimezone('UTC'),
                        ]);

                        if (in_array($action, ['restock', 'adjusted'])) {
                            $newStock = max(0, $product->stock_quantity + $quantityChange);
                            $product->update([
                                'stock_quantity' => $newStock,
                                'status' => match (true) {
                                    $newStock == 0 => 'out of stock',
                                    $newStock <= $product->low_stock_threshold => 'low stock',
                                    default => 'stock',
                                },
                                'updated_at' => $operationTime->copy()->setTimezone('UTC'),
                            ]);
                        }
                    }
                }
            }

            $currentDate->addDay();
        }
    }

    protected function fixOngoingTimeLogs($users, $timezone): void
    {
        $users->each(function ($user) use ($timezone) {
            $openLogs = TimeLog::where('user_id', $user->id)
                ->where(function ($q) {
                    $q->whereNull('end_time')
                      ->orWhere('status', 'logged_in');
                })
                ->orderByDesc('start_time')
                ->get();

            if ($openLogs->count() > 1) {
                $openLogs->skip(1)->each(function ($log) use ($timezone) {
                    $endTime = $log->start_time->copy()->setTimezone($timezone)
                                 ->addMinutes(rand(15, 480));

                    if ($endTime->gt(Carbon::now($timezone))) {
                        $endTime = Carbon::now($timezone);
                    }

                    $log->update([
                        'end_time' => $endTime->copy()->setTimezone('UTC'),
                        'status' => 'logged_out',
                        'duration' => $log->start_time->copy()->setTimezone($timezone)
                                        ->diffInMinutes($endTime),
                        'updated_at' => $endTime->copy()->setTimezone('UTC'),
                    ]);
                });
            }
        });
    }
}
