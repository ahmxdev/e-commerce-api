<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory()->state(['stock' => 100]),
            'product_name' => fake()->words(3, true),
            'unit_price' => fake()->randomFloat(2, 1, 1000),
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }
}
