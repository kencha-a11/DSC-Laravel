<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Attach 3 images to every product
        \App\Models\Product::all()->each(function ($product) {
            \App\Models\ProductImage::factory(3)->create([
                'product_id' => $product->id, // link to existing product
            ]);
        });
    }
}
