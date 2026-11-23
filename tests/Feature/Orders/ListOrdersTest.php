<?php

use App\Models\Order;
use App\Models\User;

test('index returns 401 for a guest', function (): void {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});

test('index returns only the authenticated user orders', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    Order::factory()->for($otherUser)->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('list-orders-api')->plainTextToken];

    $this->getJson('/api/v1/orders', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order->id)
        ->assertJsonMissingPath('data.0.customer')
        ->assertJsonMissingPath('data.1.id');
});
