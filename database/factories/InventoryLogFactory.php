<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Product;
use App\Models\InventoryLog;

/**
 * @extends Factory<InventoryLog>
 */
class InventoryLogFactory extends Factory
{
    public function definition(): array
    {
        // Define valid log actions
        $actions = ['created', 'update', 'restock', 'deducted', 'deleted', 'adjusted'];
        $action = $this->faker->randomElement($actions);

        // Pick random valid user and product
        $user = User::inRandomOrder()->first() ?? User::factory()->create();
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();

        // Calculate quantity change (negative if deleted)
        $quantityChange = match ($action) {
            'deleted' => -$this->faker->numberBetween(1, 10),
            'restock' => $this->faker->numberBetween(5, 50),
            default => $this->faker->numberBetween(1, 20),
        };

        return [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'action' => $action,
            'quantity_change' => $quantityChange,
            'created_at' => now()->subDays(rand(0, 365)),
            'updated_at' => now(),
        ];
    }
}
