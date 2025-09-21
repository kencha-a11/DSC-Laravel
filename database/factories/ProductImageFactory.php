<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 'product_id' => \App\Models\Product::factory(), // remove this
            'image_path' => $this->faker->imageUrl(640, 480, 'products', true),
            'is_primary' => $this->faker->boolean(),
        ];
    }
}
