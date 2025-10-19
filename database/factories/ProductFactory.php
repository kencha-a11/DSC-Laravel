<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $stock = $this->faker->numberBetween(0, 100);
        $threshold = $this->faker->numberBetween(1, 10);

        return [
            'name' => $this->faker->word(),
            'price' => $this->faker->randomFloat(2, 1, 100),
            // category_id removed if using pivot table for many-to-many
            'stock_quantity' => $stock,
            'low_stock_threshold' => $threshold,
            'status' => match (true) {
                $stock == 0 => 'out of stock',
                $stock <= $threshold => 'low stock',
                default => 'stock',
            },
        ];
    }
}
