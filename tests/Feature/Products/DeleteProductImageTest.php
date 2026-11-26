<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function deleteProductImageManagerHeaders(): array
{
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageProducts->value, 'web');
    $user->givePermissionTo($permission);

    return [
        'Authorization' => 'Bearer '.$user->createToken('product-api')->plainTextToken,
    ];
}

test('permitted user can delete an image and remaining images are reindexed', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create();
    $images = collect(range(0, 2))->map(function (int $position) use ($product): ProductImage {
        $path = 'products/'.$product->id.'/'.$position.'.jpg';
        Storage::disk('public')->put($path, 'image');

        return ProductImage::factory()->for($product)->create([
            'path' => $path,
            'position' => $position,
        ]);
    });

    $this->deleteJson(
        "/api/v1/admin/products/{$product->id}/images/{$images[0]->id}",
        [],
        deleteProductImageManagerHeaders()
    )->assertNoContent();

    $this->assertDatabaseMissing(ProductImage::class, ['id' => $images[0]->id]);
    $this->assertDatabaseHas(ProductImage::class, [
        'id' => $images[1]->id,
        'position' => 0,
    ]);
    $this->assertDatabaseHas(ProductImage::class, [
        'id' => $images[2]->id,
        'position' => 1,
    ]);
    Storage::disk('public')->assertMissing($images[0]->path);
});

test('returns 409 and keeps the last product image and file', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create();
    $path = 'products/'.$product->id.'/only.jpg';
    Storage::disk('public')->put($path, 'image');
    $image = ProductImage::factory()->for($product)->create(['path' => $path]);

    $this->deleteJson(
        "/api/v1/admin/products/{$product->id}/images/{$image->id}",
        [],
        deleteProductImageManagerHeaders()
    )
        ->assertConflict()
        ->assertJsonPath('message', 'A product must have at least one image.');

    $this->assertModelExists($image);
    Storage::disk('public')->assertExists($path);
});

test('returns 404 when deleting an image through a different product', function (): void {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $otherImage = ProductImage::factory()->for($otherProduct)->create();

    $this->deleteJson(
        "/api/v1/admin/products/{$product->id}/images/{$otherImage->id}",
        [],
        deleteProductImageManagerHeaders()
    )->assertNotFound();

    $this->assertModelExists($otherImage);
});

test('guest cannot delete product images', function (): void {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();

    $this->deleteJson("/api/v1/admin/products/{$product->id}/images/{$image->id}")
        ->assertUnauthorized();

    $this->assertModelExists($image);
});

test('user without product management permission cannot delete product images', function (): void {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();
    $user = User::factory()->create();

    $this->deleteJson("/api/v1/admin/products/{$product->id}/images/{$image->id}", [], [
        'Authorization' => 'Bearer '.$user->createToken('user-token')->plainTextToken,
    ])->assertForbidden();

    $this->assertModelExists($image);
});
