<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    $image = ProductImage::factory()->for($product)->create([
        'path' => 'products/'.$product->id.'/primary.jpg',
        'position' => 0,
    ]);

    $this->patchJson("/api/v1/admin/products/{$product->id}", ['stock' => 3], updateProductManagerHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Desk Lamp')
        ->assertJsonPath('data.price', '49.99')
        ->assertJsonPath('data.stock', 3)
        ->assertJsonPath('data.category.id', $product->category_id)
        ->assertJsonPath('data.category.name', $product->category->name)
        ->assertJsonCount(1, 'data.images')
        ->assertJsonPath('data.images.0.id', $image->id);

    $this->assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'name' => 'Desk Lamp',
        'stock' => 3,
    ]);
});

test('permitted user can change a product category', function (): void {
    $product = Product::factory()->create();
    $category = Category::factory()->create(['name' => 'Lighting']);

    $this->patchJson("/api/v1/admin/products/{$product->id}", [
        'category_id' => $category->id,
    ], updateProductManagerHeaders())
        ->assertOk()
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.category.name', 'Lighting');

    $this->assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'category_id' => $category->id,
    ]);
});

test('returns 422 when a product category is cleared', function (): void {
    $product = Product::factory()->create();
    $originalCategoryId = $product->category_id;

    $this->patchJson("/api/v1/admin/products/{$product->id}", [
        'category_id' => null,
    ], updateProductManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);

    $this->assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'category_id' => $originalCategoryId,
    ]);
});

test('returns 422 when a product category does not exist', function (): void {
    $product = Product::factory()->create();
    $originalCategoryId = $product->category_id;

    $this->patchJson("/api/v1/admin/products/{$product->id}", [
        'category_id' => 999,
    ], updateProductManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);

    $this->assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'category_id' => $originalCategoryId,
    ]);
});

test('returns 422 when product images are included in a patch request', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create(['stock' => 8]);

    $this->post("/api/v1/admin/products/{$product->id}", [
        '_method' => 'PATCH',
        'stock' => 3,
        'images' => [UploadedFile::fake()->image('side.jpg')],
    ], [
        ...updateProductManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images']);

    $this->assertSame(8, $product->fresh()->stock);
    $this->assertDatabaseCount(ProductImage::class, 0);
    $this->assertSame([], Storage::disk('public')->allFiles());
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
