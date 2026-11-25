<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function updateProductManagerHeaders(): array
{
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageProducts->value, 'web');
    $user->givePermissionTo($permission);

    return [
        'Authorization' => 'Bearer '.$user->createToken('product-api')->plainTextToken,
    ];
}

test('permitted user can partially update a product', function (): void {
    $product = Product::factory()->create([
        'name' => 'Desk Lamp',
        'price' => 49.99,
        'stock' => 8,
    ]);

    $this->patchJson("/api/v1/admin/products/{$product->id}", ['stock' => 3], updateProductManagerHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Desk Lamp')
        ->assertJsonPath('data.price', '49.99')
        ->assertJsonPath('data.stock', 3);

    $this->assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'name' => 'Desk Lamp',
        'stock' => 3,
    ]);
});

test('returns 403 and leaves product unchanged for a user without permission', function (): void {
    $product = Product::factory()->create(['stock' => 8]);
    $user = User::factory()->create();
    $headers = [
        'Authorization' => 'Bearer '.$user->createToken('user-token')->plainTextToken,
    ];

    $this->patchJson("/api/v1/admin/products/{$product->id}", ['stock' => 3], $headers)
        ->assertForbidden();

    $this->assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'stock' => 8,
    ]);
});

test('returns 422 and preserves stock when an update sets negative stock', function (): void {
    $product = Product::factory()->create(['stock' => 8]);

    $this->patchJson(
        "/api/v1/admin/products/{$product->id}",
        ['stock' => -1],
        updateProductManagerHeaders()
    )
        ->assertUnprocessable()
        ->assertJsonPath('errors.stock.0', 'The stock field must be at least 0.');

    $this->assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'stock' => 8,
    ]);
});

test('returns 422 when an update contains no product fields', function (): void {
    $product = Product::factory()->create(['stock' => 8]);

    $this->patchJson(
        "/api/v1/admin/products/{$product->id}",
        [],
        updateProductManagerHeaders()
    )
        ->assertUnprocessable()
        ->assertJsonPath('errors.product.0', 'At least one product field must be provided.');

    $this->assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'stock' => 8,
    ]);
});
