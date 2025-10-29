<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;
use App\Models\Sale;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        $snapshotQuantity = $this->faker->numberBetween(1, 5);

        // Simulate deleted product
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
