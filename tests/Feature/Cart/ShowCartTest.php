<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
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
    $primaryImage = ProductImage::factory()->for($product)->create([
        'path' => 'products/'.$product->id.'/primary.jpg',
        'position' => 0,
    ]);
    ProductImage::factory()->for($product)->create([
        'path' => 'products/'.$product->id.'/secondary.jpg',
        'position' => 1,
    ]);
    CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);
    CartItem::factory()->for($otherCart)->for($otherProduct)->create(['quantity' => 1]);

    $this->getJson('/api/v1/cart', showCartHeaders($user))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product.id', $product->id)
        ->assertJsonPath('data.items.0.product.category.id', $product->category_id)
        ->assertJsonCount(1, 'data.items.0.product.images')
        ->assertJsonPath('data.items.0.product.images.0.id', $primaryImage->id)
        ->assertJsonPath('data.items.0.product.images.0.is_primary', true)
        ->assertJsonPath('data.subtotal', '20.00');
});

test('show calculates the subtotal across multiple products', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $firstProduct = Product::factory()->create(['price' => '12.50', 'stock' => 8]);
    $secondProduct = Product::factory()->create(['price' => '7.25', 'stock' => 6]);
    CartItem::factory()->for($cart)->for($firstProduct)->create(['quantity' => 2]);
    CartItem::factory()->for($cart)->for($secondProduct)->create(['quantity' => 3]);

    $response = $this->getJson('/api/v1/cart', showCartHeaders($user))
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.subtotal', '46.75');

    $itemsByProductId = collect($response->json('data.items'))->keyBy('product.id');

    expect($itemsByProductId->get($firstProduct->id, [])['line_total'] ?? null)->toBe('25.00');
    expect($itemsByProductId->get($secondProduct->id, [])['line_total'] ?? null)->toBe('21.75');
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
