<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function addProductImagesManagerHeaders(): array
{
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageProducts->value, 'web');
    $user->givePermissionTo($permission);

    return [
        'Authorization' => 'Bearer '.$user->createToken('product-api')->plainTextToken,
    ];
}

test('permitted user can append product images', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create();
    ProductImage::factory()->for($product)->create([
        'path' => 'products/'.$product->id.'/existing.jpg',
        'position' => 0,
    ]);

    $response = $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [
            UploadedFile::fake()->image('side.jpg'),
            UploadedFile::fake()->image('back.jpg'),
        ],
    ], [
        ...addProductImagesManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertCreated()
        ->assertJsonPath('data.category.id', $product->category_id)
        ->assertJsonCount(3, 'data.images')
        ->assertJsonPath('data.images.0.position', 0)
        ->assertJsonPath('data.images.0.is_primary', true)
        ->assertJsonPath('data.images.1.position', 1)
        ->assertJsonPath('data.images.1.is_primary', false)
        ->assertJsonPath('data.images.2.position', 2);

    $this->assertDatabaseCount(ProductImage::class, 3);
    $newImages = ProductImage::query()->whereBelongsTo($product)->where('position', '>', 0)->get();

    foreach ($newImages as $image) {
        Storage::disk('public')->assertExists($image->path);
    }

    $this->assertSame($product->id, $response->json('data.id'));
});

test('returns conflict when appended images would exceed eight for the product', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create();

    foreach (range(0, 6) as $position) {
        ProductImage::factory()->for($product)->create([
            'path' => 'products/'.$product->id.'/'.$position.'.jpg',
            'position' => $position,
        ]);
    }

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [
            UploadedFile::fake()->image('side.jpg'),
            UploadedFile::fake()->image('back.jpg'),
        ],
    ], [
        ...addProductImagesManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertConflict()
        ->assertJsonPath('message', 'A product cannot have more than 8 images.');

    $this->assertDatabaseCount(ProductImage::class, 7);
    $this->assertSame([], Storage::disk('public')->allFiles());
});

test('returns 422 for invalid appended image files', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
    ], [
        ...addProductImagesManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images.0']);

    $this->assertDatabaseCount(ProductImage::class, 0);
    $this->assertSame([], Storage::disk('public')->allFiles());
});

test('returns 422 when the image collection is missing', function (): void {
    $product = Product::factory()->create();

    $this->postJson("/api/v1/admin/products/{$product->id}/images", [], addProductImagesManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images']);

    $this->assertDatabaseCount(ProductImage::class, 0);
});

test('returns 422 when one request contains more than eight images', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => collect(range(1, 9))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image("image-{$index}.jpg"))
            ->all(),
    ], [
        ...addProductImagesManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images']);

    $this->assertDatabaseCount(ProductImage::class, 0);
    $this->assertSame([], Storage::disk('public')->allFiles());
});

test('returns 422 when an appended image exceeds five megabytes', function (): void {
    Storage::fake('public');
    $product = Product::factory()->create();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('large.jpg')->size(5121)],
    ], [
        ...addProductImagesManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images.0']);

    $this->assertDatabaseCount(ProductImage::class, 0);
    $this->assertSame([], Storage::disk('public')->allFiles());
});

test('guest cannot append product images', function (): void {
    $product = Product::factory()->create();

    $this->postJson("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('side.jpg')],
    ])->assertUnauthorized();

    $this->assertDatabaseCount(ProductImage::class, 0);
});

test('user without product management permission cannot append product images', function (): void {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $this->postJson("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('side.jpg')],
    ], [
        'Authorization' => 'Bearer '.$user->createToken('user-token')->plainTextToken,
    ])->assertForbidden();

    $this->assertDatabaseCount(ProductImage::class, 0);
});
