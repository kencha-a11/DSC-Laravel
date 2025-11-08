<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Product;

class InventoryLogFactory extends Factory
{
    public function definition(): array
    {
        // Define valid actions
        $actions = ['created', 'update', 'restock', 'deducted', 'deleted', 'adjusted'];
        $action = $this->faker->randomElement($actions);

        // Select or create related records
        $user = User::inRandomOrder()->first() ?? User::factory()->create();
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();

        // Simulate realistic quantity changes based on action
        $quantityChange = match ($action) {
            'deleted' => -$this->faker->numberBetween(1, 10),
            'deducted' => -$this->faker->numberBetween(1, 10),
            'restock' => $this->faker->numberBetween(20, 100),
            'adjusted' => $this->faker->numberBetween(-10, 10),
            'update' => $this->faker->numberBetween(1, 5),
            default => $this->faker->numberBetween(10, 50),
        };

        // Human-readable snapshot name based on the action
        $snapshotName = match ($action) {
            'created' => 'Initial Stock Creation',
            'update' => 'Stock Info Updated',
            'restock' => 'Restocked Inventory',
            'deducted' => 'Stock Deduction from Sale',
            'deleted' => 'Product Deletion',
            'adjusted' => 'Inventory Adjustment',
        };

        // Random timestamp (within past 3 months to now)
        $createdAt = now()->subDays(rand(0, 90));

        return [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'action' => $action,
            'quantity_change' => $quantityChange,
            'snapshot_name' => $snapshotName,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
