<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('permitted user can delete a product', function (): void {
    $product = Product::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageProducts->value, 'web');
    $user->givePermissionTo($permission);
    $headers = [
        'Authorization' => 'Bearer '.$user->createToken('product-api')->plainTextToken,
    ];

    $this->deleteJson("/api/v1/products/{$product->id}", [], $headers)
        ->assertNoContent();

    $this->assertModelMissing($product);
});

test('guest cannot delete a product', function (): void {
    $product = Product::factory()->create();

    $this->deleteJson("/api/v1/products/{$product->id}")
        ->assertUnauthorized();

    $this->assertModelExists($product);
});

test('returns 403 and keeps product for a user without permission', function (): void {
    $product = Product::factory()->create();
    $user = User::factory()->create();
    $headers = [
        'Authorization' => 'Bearer '.$user->createToken('user-token')->plainTextToken,
    ];

    $this->deleteJson("/api/v1/products/{$product->id}", [], $headers)
        ->assertForbidden();

    $this->assertModelExists($product);
});

test('returns 409 and keeps product while it is in a cart', function (): void {
    $product = Product::factory()->create(['stock' => 5]);
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);
    $permission = Permission::findOrCreate(PermissionEnum::ManageProducts->value, 'web');
    $user->givePermissionTo($permission);
    $headers = [
        'Authorization' => 'Bearer '.$user->createToken('product-api')->plainTextToken,
    ];

    $this->deleteJson("/api/v1/products/{$product->id}", [], $headers)
        ->assertConflict()
        ->assertJsonPath('message', 'The product cannot be deleted while it is in a cart.');

    $this->assertModelExists($product);
});
