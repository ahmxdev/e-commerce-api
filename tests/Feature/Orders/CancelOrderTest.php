<?php

use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

test('cancel returns 401 for a guest', function (): void {
    $order = Order::factory()->create();

    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertUnauthorized();
});

test('cancel marks a pending order cancelled and restores its stock', function (): void {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => OrderStatusEnum::Pending]);
    $product = Product::factory()->create(['stock' => 3]);
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('cancel-order-api')->plainTextToken];

    $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatusEnum::Cancelled->value);

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatusEnum::Cancelled->value]);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    $this->assertDatabaseCount('order_items', 1);
});

test('cancel returns 404 for another users order', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($otherUser)->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('cancel-order-api')->plainTextToken];

    $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $headers)->assertNotFound();

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatusEnum::Pending->value]);
});

test('cancel returns 409 for an order that is no longer pending', function (): void {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => OrderStatusEnum::Processing]);
    $product = Product::factory()->create(['stock' => 3]);
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('cancel-order-api')->plainTextToken];

    $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $headers)
        ->assertConflict()
        ->assertJsonPath('message', 'Only pending orders can be cancelled by the customer.');

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatusEnum::Processing->value]);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
});

test('cancel does not restore stock a second time for an already cancelled order', function (): void {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => OrderStatusEnum::Pending]);
    $product = Product::factory()->create(['stock' => 3]);
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('cancel-order-api')->plainTextToken];

    $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $headers)->assertOk();
    $this->postJson("/api/v1/orders/{$order->id}/cancel", [], $headers)->assertConflict();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
});
