<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function reorderProductImagesManagerHeaders(): array
{
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageProducts->value, 'web');
    $user->givePermissionTo($permission);

    return [
        'Authorization' => 'Bearer '.$user->createToken('product-api')->plainTextToken,
    ];
}

test('permitted user can reorder all images and make a different image primary', function (): void {
    $product = Product::factory()->create();
    $images = collect(range(0, 2))->map(fn (int $position): ProductImage => ProductImage::factory()
        ->for($product)
        ->create(['position' => $position]));

    $this->patchJson("/api/v1/admin/products/{$product->id}/images/reorder", [
        'image_ids' => [$images[2]->id, $images[0]->id, $images[1]->id],
    ], reorderProductImagesManagerHeaders())
        ->assertOk()
        ->assertJsonPath('data.category.id', $product->category_id)
        ->assertJsonPath('data.images.0.id', $images[2]->id)
        ->assertJsonPath('data.images.0.position', 0)
        ->assertJsonPath('data.images.0.is_primary', true)
        ->assertJsonPath('data.images.1.id', $images[0]->id)
        ->assertJsonPath('data.images.1.position', 1)
        ->assertJsonPath('data.images.2.id', $images[1]->id)
        ->assertJsonPath('data.images.2.position', 2);

    $this->assertDatabaseHas(ProductImage::class, [
        'id' => $images[2]->id,
        'position' => 0,
    ]);
});

test('returns 422 when reorder omits a product image', function (): void {
    $product = Product::factory()->create();
    $images = ProductImage::factory()->count(2)->for($product)->sequence(
        ['position' => 0],
        ['position' => 1],
    )->create();

    $this->patchJson("/api/v1/admin/products/{$product->id}/images/reorder", [
        'image_ids' => [$images[0]->id],
    ], reorderProductImagesManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image_ids']);

    $this->assertDatabaseHas(ProductImage::class, [
        'id' => $images[0]->id,
        'position' => 0,
    ]);
    $this->assertDatabaseHas(ProductImage::class, [
        'id' => $images[1]->id,
        'position' => 1,
    ]);
});

test('returns 422 when reorder contains an image from another product', function (): void {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();
    $otherImage = ProductImage::factory()->for($otherProduct)->create();

    $this->patchJson("/api/v1/admin/products/{$product->id}/images/reorder", [
        'image_ids' => [$image->id, $otherImage->id],
    ], reorderProductImagesManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image_ids']);

    $this->assertDatabaseHas(ProductImage::class, [
        'id' => $image->id,
        'position' => 0,
    ]);
});

test('returns 422 when reorder contains a duplicate image id', function (): void {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();

    $this->patchJson("/api/v1/admin/products/{$product->id}/images/reorder", [
        'image_ids' => [$image->id, $image->id],
    ], reorderProductImagesManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image_ids.1']);

    $this->assertDatabaseHas(ProductImage::class, [
        'id' => $image->id,
        'position' => 0,
    ]);
});

test('returns 422 when reorder image ids are missing', function (): void {
    $product = Product::factory()->create();

    $this->patchJson("/api/v1/admin/products/{$product->id}/images/reorder", [], reorderProductImagesManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image_ids']);
});

test('guest cannot reorder product images', function (): void {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();

    $this->patchJson("/api/v1/admin/products/{$product->id}/images/reorder", [
        'image_ids' => [$image->id],
    ])->assertUnauthorized();
});

test('user without product management permission cannot reorder product images', function (): void {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();
    $user = User::factory()->create();

    $this->patchJson("/api/v1/admin/products/{$product->id}/images/reorder", [
        'image_ids' => [$image->id],
    ], [
        'Authorization' => 'Bearer '.$user->createToken('user-token')->plainTextToken,
    ])->assertForbidden();
});
