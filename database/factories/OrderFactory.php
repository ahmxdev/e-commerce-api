<?php

namespace Database\Factories;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentMethodEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatusEnum::Pending->value,
            'payment_method' => PaymentMethodEnum::CashOnDelivery->value,
            'shipping_address' => fake()->address(),
            'total_amount' => fake()->randomFloat(2, 1, 1000),
        ];
    }
}
