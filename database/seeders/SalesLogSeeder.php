<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sale;
use App\Models\SalesLog;
use Carbon\Carbon;

class SalesLogSeeder extends Seeder
{
    /**
     * Seed the sales logs for the dashboard.
     */
    public function run(): void
    {
        $sales = Sale::with('saleItems')->get();

        foreach ($sales as $sale) {
            // Calculate total amount from related sale items
            $totalAmount = $sale->saleItems->sum('subtotal');

            // Create a corresponding SalesLog
            SalesLog::create([
                'user_id' => $sale->user_id,
                'sale_id' => $sale->id,
                'amount' => $totalAmount,
                'created_at' => $sale->created_at,
                'updated_at' => $sale->updated_at,
            ]);
        }

        $this->command->info("✅ Created " . $sales->count() . " SalesLogs matching SaleItems");
    }
}
