<?php

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentMethodEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

test('store returns 401 for a guest', function (): void {
    $this->postJson('/api/v1/orders', ['shipping_address' => '10 River Street'])
        ->assertUnauthorized();
});

test('store returns 422 when shipping address is missing', function (): void {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('store-order-api')->plainTextToken];

    $this->postJson('/api/v1/orders', [], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.shipping_address.0', 'The shipping address field is required.');

    $this->assertDatabaseCount('orders', 0);
});

test('store returns 422 when shipping address is not a string', function (): void {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('store-order-api')->plainTextToken];

    $this->postJson('/api/v1/orders', ['shipping_address' => ['10 River Street']], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.shipping_address.0', 'The shipping address field must be a string.');
});

test('store returns 422 when shipping address exceeds 5000 characters', function (): void {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('store-order-api')->plainTextToken];

    $this->postJson('/api/v1/orders', ['shipping_address' => str_repeat('a', 5001)], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.shipping_address.0', 'The shipping address field must not be greater than 5000 characters.');
});

test('store returns 409 when the authenticated user has no cart items', function (): void {
    $user = User::factory()->create();
    Cart::factory()->for($user)->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('store-order-api')->plainTextToken];

    $this->postJson('/api/v1/orders', ['shipping_address' => '10 River Street'], $headers)
        ->assertConflict()
        ->assertJsonPath('message', 'Your cart is empty.');

    $this->assertDatabaseCount('orders', 0);
});

test('store returns 409 when the authenticated user has no cart', function (): void {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('store-order-api')->plainTextToken];

    $this->postJson('/api/v1/orders', ['shipping_address' => '10 River Street'], $headers)
        ->assertConflict()
        ->assertJsonPath('message', 'Your cart is empty.');

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('carts', 0);
});

test('store creates an order from cart snapshots and clears the cart', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $product = Product::factory()->create([
        'name' => 'Canvas Backpack',
        'price' => '12.50',
        'stock' => 8,
    ]);
    CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('store-order-api')->plainTextToken];

    $response = $this->postJson('/api/v1/orders', [
        'shipping_address' => '10 River Street, Cairo',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', OrderStatusEnum::Pending->value)
        ->assertJsonPath('data.payment_method', PaymentMethodEnum::CashOnDelivery->value)
        ->assertJsonPath('data.shipping_address', '10 River Street, Cairo')
        ->assertJsonPath('data.total_amount', '25.00')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product_name', 'Canvas Backpack')
        ->assertJsonPath('data.items.0.unit_price', '12.50')
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.line_total', '25.00');

    $orderId = $response->json('data.id');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'user_id' => $user->id,
        'status' => OrderStatusEnum::Pending->value,
        'payment_method' => PaymentMethodEnum::CashOnDelivery->value,
        'total_amount' => '25.00',
    ]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => $product->id,
        'product_name' => 'Canvas Backpack',
        'unit_price' => '12.50',
        'quantity' => 2,
    ]);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 6]);
    $this->assertDatabaseCount('cart_items', 0);
    $this->assertDatabaseCount(Order::class, 1);
    $this->assertDatabaseCount(OrderItem::class, 1);
});

test('store calculates the order total across multiple products', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $firstProduct = Product::factory()->create([
        'name' => 'Canvas Backpack',
        'price' => '12.50',
        'stock' => 8,
    ]);
    $secondProduct = Product::factory()->create([
        'name' => 'Travel Mug',
        'price' => '7.25',
        'stock' => 6,
    ]);
    CartItem::factory()->for($cart)->for($firstProduct)->create(['quantity' => 2]);
    CartItem::factory()->for($cart)->for($secondProduct)->create(['quantity' => 3]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('store-order-api')->plainTextToken];

    $response = $this->postJson('/api/v1/orders', [
        'shipping_address' => '10 River Street, Cairo',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '46.75')
        ->assertJsonCount(2, 'data.items');

    $orderId = $response->json('data.id');
    $orderItemsByProductId = collect($response->json('data.items'))->keyBy('product_id');

    expect($orderItemsByProductId->get($firstProduct->id, [])['line_total'] ?? null)->toBe('25.00');
    expect($orderItemsByProductId->get($secondProduct->id, [])['line_total'] ?? null)->toBe('21.75');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'user_id' => $user->id,
        'total_amount' => '46.75',
    ]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => $firstProduct->id,
        'unit_price' => '12.50',
        'quantity' => 2,
    ]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => $secondProduct->id,
        'unit_price' => '7.25',
        'quantity' => 3,
    ]);
    $this->assertDatabaseHas('products', ['id' => $firstProduct->id, 'stock' => 6]);
    $this->assertDatabaseHas('products', ['id' => $secondProduct->id, 'stock' => 3]);
    $this->assertDatabaseCount('cart_items', 0);
});

test('store returns 409 and keeps the cart when stock is insufficient', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $product = Product::factory()->create(['stock' => 1]);
    CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('store-order-api')->plainTextToken];

    $this->postJson('/api/v1/orders', ['shipping_address' => '10 River Street'], $headers)
        ->assertConflict()
        ->assertJsonPath('message', "Insufficient stock for product: {$product->name}.");

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
});
