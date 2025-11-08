<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;
use App\Models\Sale;

class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        // Pick a random existing product
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();
        $snapshotQuantity = $this->faker->numberBetween(1, 5);

        // 20% chance the product is "deleted"
        $productId = $this->faker->boolean(20) ? null : $product->id;

        return [
            'product_id' => $productId,
            'quantity' => $snapshotQuantity,
            'price' => $product->price,
            'subtotal' => $snapshotQuantity * $product->price,
            'snapshot_name' => $product->name ?? 'Deleted Product',
            'snapshot_quantity' => $snapshotQuantity,
            'snapshot_price' => $product->price ?? 0,
        ];
    }
}
