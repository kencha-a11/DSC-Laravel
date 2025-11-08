<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sale;
use App\Models\SaleItem;

class SaleItemSeeder extends Seeder
{
    public function run(): void
    {
        // Attach 1–4 items per existing Sale
        $sales = Sale::all();

        foreach ($sales as $sale) {
            SaleItem::factory(rand(1, 4))
                ->for($sale, 'sale') // sets sale_id
                ->create();
        }
    }
}
