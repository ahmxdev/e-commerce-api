<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

function showCartHeaders(User $user): array
{
    return [
        'Authorization' => 'Bearer '.$user->createToken('show-cart-api')->plainTextToken,
    ];
}

test('show returns 401 for a guest', function (): void {
    $this->getJson('/api/v1/cart')->assertUnauthorized();
});

test('show returns 401 for an invalid bearer token', function (): void {
    $this->getJson('/api/v1/cart', [
        'Authorization' => 'Bearer invalid-token',
    ])->assertUnauthorized();
});

test('show returns only the authenticated user cart items', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $otherCart = Cart::factory()->for($otherUser)->create();
    $product = Product::factory()->create(['price' => '10.00', 'stock' => 5]);
    $otherProduct = Product::factory()->create(['price' => '25.00', 'stock' => 5]);
    CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);
    CartItem::factory()->for($otherCart)->for($otherProduct)->create(['quantity' => 1]);

    $this->getJson('/api/v1/cart', showCartHeaders($user))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product.id', $product->id)
        ->assertJsonPath('data.subtotal', '20.00');
});

test('show creates an empty cart for the authenticated user', function (): void {
    $user = User::factory()->create();

    $response = $this->getJson('/api/v1/cart', showCartHeaders($user))
        ->assertOk()
        ->assertJsonCount(0, 'data.items')
        ->assertJsonPath('data.subtotal', '0.00');

    $this->assertDatabaseHas('carts', [
        'id' => $response->json('data.id'),
        'user_id' => $user->id,
    ]);
});
