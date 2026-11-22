<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

function deleteCartItemHeaders(User $user): array
{
    return [
        'Authorization' => 'Bearer '.$user->createToken('delete-cart-item-api')->plainTextToken,
    ];
}

test('destroy returns 401 for a guest', function (): void {
    $this->deleteJson('/api/v1/cart/items/1', [])->assertUnauthorized();
});

test('destroy removes an item from the authenticated user cart', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $product = Product::factory()->create(['stock' => 5]);
    $cartItem = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

    $this->deleteJson("/api/v1/cart/items/{$cartItem->id}", [], deleteCartItemHeaders($user))
        ->assertOk()
        ->assertJsonCount(0, 'data.items')
        ->assertJsonPath('data.subtotal', '0.00');

    $this->assertModelMissing($cartItem);
});

test('destroy returns 404 for another user cart item', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCart = Cart::factory()->for($otherUser)->create();
    $product = Product::factory()->create(['stock' => 5]);
    $cartItem = CartItem::factory()->for($otherCart)->for($product)->create(['quantity' => 2]);

    $this->deleteJson("/api/v1/cart/items/{$cartItem->id}", [], deleteCartItemHeaders($user))
        ->assertNotFound();

    $this->assertModelExists($cartItem);
});
