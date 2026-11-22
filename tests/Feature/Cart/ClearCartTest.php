<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

function clearCartHeaders(User $user): array
{
    return [
        'Authorization' => 'Bearer '.$user->createToken('clear-cart-api')->plainTextToken,
    ];
}

test('clear returns 401 for a guest', function (): void {
    $this->deleteJson('/api/v1/cart', [])->assertUnauthorized();
});

test('clear removes every item from the authenticated user cart', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $products = Product::factory()->count(2)->create(['stock' => 5]);
    CartItem::factory()->for($cart)->for($products[0])->create(['quantity' => 1]);
    CartItem::factory()->for($cart)->for($products[1])->create(['quantity' => 2]);

    $this->deleteJson('/api/v1/cart', [], clearCartHeaders($user))
        ->assertOk()
        ->assertJsonCount(0, 'data.items')
        ->assertJsonPath('data.subtotal', '0.00');

    $this->assertDatabaseCount(CartItem::class, 0);
});

test('clear does not remove another user cart items', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCart = Cart::factory()->for($otherUser)->create();
    $product = Product::factory()->create(['stock' => 5]);
    $otherCartItem = CartItem::factory()->for($otherCart)->for($product)->create(['quantity' => 2]);

    $this->deleteJson('/api/v1/cart', [], clearCartHeaders($user))
        ->assertOk()
        ->assertJsonCount(0, 'data.items');

    $this->assertModelExists($otherCartItem);
});
