<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

function storeCartItemHeaders(User $user): array
{
    return [
        'Authorization' => 'Bearer '.$user->createToken('store-cart-item-api')->plainTextToken,
    ];
}

test('store returns 401 for a guest', function (): void {
    $this->postJson('/api/v1/cart/items', [])->assertUnauthorized();
});

test('store adds one unit to the authenticated user cart', function (): void {
    $user = User::factory()->create();
    Cart::factory()->for($user)->create();
    $product = Product::factory()->create(['price' => '12.50', 'stock' => 4]);

    $response = $this->postJson('/api/v1/cart/items', ['product_id' => $product->id], storeCartItemHeaders($user))
        ->assertCreated()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product.id', $product->id)
        ->assertJsonPath('data.items.0.quantity', 1)
        ->assertJsonPath('data.items.0.unit_price', '12.50')
        ->assertJsonPath('data.items.0.line_total', '12.50')
        ->assertJsonPath('data.subtotal', '12.50');

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $response->json('data.id'),
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
});

test('store increments an existing product quantity by one', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => '8.00', 'stock' => 2]);
    $headers = storeCartItemHeaders($user);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id], $headers)
        ->assertCreated()
        ->assertJsonPath('data.items.0.quantity', 1);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id], $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.line_total', '16.00')
        ->assertJsonPath('data.subtotal', '16.00');

    $this->assertDatabaseCount(CartItem::class, 1);
    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
});

test('store returns 409 and preserves quantity when an increment exceeds stock', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $product = Product::factory()->create(['stock' => 2]);
    CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id], storeCartItemHeaders($user))
        ->assertConflict()
        ->assertJsonPath('message', 'Product is out of stock.');

    $this->assertDatabaseCount(CartItem::class, 1);
    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 2,
    ]);
});

test('store returns 409 and rolls back for an out-of-stock product', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 0]);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id], storeCartItemHeaders($user))
        ->assertConflict()
        ->assertJsonPath('message', 'Product is out of stock.');

    $this->assertDatabaseCount(Cart::class, 0);
    $this->assertDatabaseCount(CartItem::class, 0);
});

test('store returns 422 when product id is missing', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/cart/items', [], storeCartItemHeaders($user))
        ->assertUnprocessable()
        ->assertJsonPath('errors.product_id.0', 'The product id field is required.');

    $this->assertDatabaseCount(Cart::class, 0);
});

test('store returns 422 when product id is not an integer', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/cart/items', ['product_id' => 'not-an-id'], storeCartItemHeaders($user))
        ->assertUnprocessable()
        ->assertJsonPath('errors.product_id.0', 'The product id field must be an integer.');

    $this->assertDatabaseCount(Cart::class, 0);
});

test('store returns 422 when product id does not exist', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/cart/items', ['product_id' => 999999], storeCartItemHeaders($user))
        ->assertUnprocessable()
        ->assertJsonPath('errors.product_id.0', 'The selected product id is invalid.');

    $this->assertDatabaseCount(Cart::class, 0);
});
