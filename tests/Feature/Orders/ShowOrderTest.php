<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

test('show returns 401 for a guest', function (): void {
    $order = Order::factory()->create();

    $this->getJson("/api/v1/orders/{$order->id}")->assertUnauthorized();
});

test('show returns the authenticated users order and item snapshots', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->for($user)->create();
    $item = OrderItem::factory()->for($order)->for($product)->create([
        'product_name' => 'Saved Product Name',
        'unit_price' => '19.95',
        'quantity' => 2,
    ]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('show-order-api')->plainTextToken];

    $this->getJson("/api/v1/orders/{$order->id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $item->id)
        ->assertJsonPath('data.items.0.product_name', 'Saved Product Name')
        ->assertJsonPath('data.items.0.unit_price', '19.95')
        ->assertJsonPath('data.items.0.line_total', '39.90');
});

test('show returns 404 for another users order', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($otherUser)->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('show-order-api')->plainTextToken];

    $this->getJson("/api/v1/orders/{$order->id}", $headers)->assertNotFound();
});
