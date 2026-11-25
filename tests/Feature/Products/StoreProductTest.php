<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function storeProductManagerHeaders(): array
{
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageProducts->value, 'web');
    $user->givePermissionTo($permission);

    return [
        'Authorization' => 'Bearer '.$user->createToken('product-api')->plainTextToken,
    ];
}

test('permitted user can create a product', function (): void {
    $productData = [
        'name' => 'Desk Lamp',
        'description' => 'Adjustable reading lamp.',
        'price' => 49.99,
        'stock' => 5,
    ];

    $response = $this->postJson('/api/v1/admin/products', $productData, storeProductManagerHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Desk Lamp')
        ->assertJsonPath('data.price', '49.99');

    $product = Product::query()->findOrFail($response->json('data.id'));
    $this->assertModelExists($product);
});

test('guest cannot create a product', function (): void {
    $this->postJson('/api/v1/admin/products', [])
        ->assertUnauthorized();

    $this->assertDatabaseCount(Product::class, 0);
});

test('returns 422 when required product fields are missing', function (): void {
    $this->postJson('/api/v1/admin/products', [], storeProductManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'description', 'price', 'stock'])
        ->assertJsonPath('errors.name.0', 'The name field is required.');

    $this->assertDatabaseCount(Product::class, 0);
});

test('returns 422 for invalid product prices and stock values', function (string $field, int|float $value, string $message): void {
    $productData = [
        'name' => 'Desk Lamp',
        'description' => 'Adjustable reading lamp.',
        'price' => 49.99,
        'stock' => 5,
    ];
    $productData[$field] = $value;

    $this->postJson('/api/v1/admin/products', $productData, storeProductManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonPath("errors.{$field}.0", $message);

    $this->assertDatabaseCount(Product::class, 0);
})->with([
    'negative price' => ['price', -1, 'The price field must be at least 0.'],
    'price with excess decimal places' => ['price', 49.999, 'The price field must have 0-2 decimal places.'],
    'price beyond decimal precision' => ['price', 100000000, 'The price field must not be greater than 99999999.99.'],
    'negative stock' => ['stock', -1, 'The stock field must be at least 0.'],
    'fractional stock' => ['stock', 1.5, 'The stock field must be an integer.'],
    'stock beyond integer precision' => ['stock', 2147483648, 'The stock field must not be greater than 2147483647.'],
]);
