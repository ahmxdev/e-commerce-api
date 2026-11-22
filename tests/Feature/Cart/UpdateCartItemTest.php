<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

function updateCartItemHeaders(User $user): array
{
    return [
        'Authorization' => 'Bearer '.$user->createToken('update-cart-item-api')->plainTextToken,
    ];
}

test('update returns 401 for a guest', function (): void {
    $this->patchJson('/api/v1/cart/items/1', ['quantity' => 2])
        ->assertUnauthorized();
});

test('update sets the quantity and recalculates totals', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $product = Product::factory()->create(['price' => '12.50', 'stock' => 5]);
    $cartItem = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

    $this->patchJson("/api/v1/cart/items/{$cartItem->id}", ['quantity' => 3], updateCartItemHeaders($user))
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', 3)
        ->assertJsonPath('data.items.0.line_total', '37.50')
        ->assertJsonPath('data.subtotal', '37.50');

    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => 3,
    ]);
});

test('update returns 409 and preserves quantity above available stock', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $product = Product::factory()->create(['stock' => 1]);
    $cartItem = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

    $this->patchJson("/api/v1/cart/items/{$cartItem->id}", ['quantity' => 2], updateCartItemHeaders($user))
        ->assertConflict()
        ->assertJsonPath('message', 'The requested quantity exceeds available stock.');

    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => 1,
    ]);
});

test('update returns 422 for invalid quantities', function (array $payload, string $message): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $product = Product::factory()->create(['stock' => 5]);
    $cartItem = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

    $this->patchJson("/api/v1/cart/items/{$cartItem->id}", $payload, updateCartItemHeaders($user))
        ->assertUnprocessable()
        ->assertJsonPath('errors.quantity.0', $message);

    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => 2,
    ]);
})->with([
    'missing quantity' => [[], 'The quantity field is required.'],
    'zero quantity' => [['quantity' => 0], 'The quantity field must be at least 1.'],
    'fractional quantity' => [['quantity' => 1.5], 'The quantity field must be an integer.'],
    'quantity above the column limit' => [['quantity' => 2147483648], 'The quantity field must not be greater than 2147483647.'],
]);

test('update returns 404 for another user cart item', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCart = Cart::factory()->for($otherUser)->create();
    $product = Product::factory()->create(['stock' => 5]);
    $cartItem = CartItem::factory()->for($otherCart)->for($product)->create(['quantity' => 2]);

    $this->patchJson("/api/v1/cart/items/{$cartItem->id}", ['quantity' => 3], updateCartItemHeaders($user))
        ->assertNotFound();

    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => 2,
    ]);
});
