<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Product\ProductImageService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    Storage::fake('public');

    $productData = [
        'name' => 'Desk Lamp',
        'description' => 'Adjustable reading lamp.',
        'price' => 49.99,
        'stock' => 5,
        'images' => [
            UploadedFile::fake()->image('front.jpg'),
            UploadedFile::fake()->image('side.png'),
        ],
    ];

    $response = $this->post('/api/v1/admin/products', $productData, [
        ...storeProductManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Desk Lamp')
        ->assertJsonPath('data.price', '49.99')
        ->assertJsonCount(2, 'data.images')
        ->assertJsonPath('data.images.0.position', 0)
        ->assertJsonPath('data.images.0.is_primary', true)
        ->assertJsonPath('data.images.1.position', 1)
        ->assertJsonPath('data.images.1.is_primary', false);

    $product = Product::query()->findOrFail($response->json('data.id'));
    $this->assertModelExists($product);
    $this->assertDatabaseCount(ProductImage::class, 2);

    foreach ($product->images()->get() as $image) {
        Storage::disk('public')->assertExists($image->path);
    }

    $primaryImage = $product->images()->firstOrFail();
    $this->assertSame(
        Storage::disk('public')->url($primaryImage->path),
        $response->json('data.images.0.url')
    );
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

test('returns 422 when product images are missing', function (): void {
    $this->postJson('/api/v1/admin/products', [
        'name' => 'Desk Lamp',
        'description' => 'Adjustable reading lamp.',
        'price' => 49.99,
        'stock' => 5,
    ], storeProductManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images']);

    $this->assertDatabaseCount(Product::class, 0);
});

test('returns 422 when product image files are invalid', function (): void {
    Storage::fake('public');

    $this->post('/api/v1/admin/products', [
        'name' => 'Desk Lamp',
        'description' => 'Adjustable reading lamp.',
        'price' => 49.99,
        'stock' => 5,
        'images' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
    ], [
        ...storeProductManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images.0']);

    $this->assertDatabaseCount(Product::class, 0);
    $this->assertDatabaseCount(ProductImage::class, 0);
    $this->assertSame([], Storage::disk('public')->allFiles());
});

test('returns 422 when product creation contains more than eight images', function (): void {
    Storage::fake('public');

    $this->post('/api/v1/admin/products', [
        'name' => 'Desk Lamp',
        'description' => 'Adjustable reading lamp.',
        'price' => 49.99,
        'stock' => 5,
        'images' => collect(range(1, 9))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image("image-{$index}.jpg"))
            ->all(),
    ], [
        ...storeProductManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images']);

    $this->assertDatabaseCount(Product::class, 0);
    $this->assertSame([], Storage::disk('public')->allFiles());
});

test('returns 422 when a product image exceeds five megabytes', function (): void {
    Storage::fake('public');

    $this->post('/api/v1/admin/products', [
        'name' => 'Desk Lamp',
        'description' => 'Adjustable reading lamp.',
        'price' => 49.99,
        'stock' => 5,
        'images' => [UploadedFile::fake()->image('large.jpg')->size(5121)],
    ], [
        ...storeProductManagerHeaders(),
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images.0']);

    $this->assertDatabaseCount(Product::class, 0);
    $this->assertSame([], Storage::disk('public')->allFiles());
});

test('removes uploaded files and database records when saving a product image fails', function (): void {
    Storage::fake('public');
    $imageFiles = [
        UploadedFile::fake()->image('front.jpg'),
        UploadedFile::fake()->image('side.jpg'),
    ];
    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('a', $length));

    try {
        expect(fn () => app(ProductImageService::class)->createProductWithImages([
            'name' => 'Desk Lamp',
            'description' => 'Adjustable reading lamp.',
            'price' => 49.99,
            'stock' => 5,
        ], $imageFiles))->toThrow(QueryException::class);
    } finally {
        Str::createRandomStringsNormally();
    }

    $this->assertDatabaseCount(Product::class, 0);
    $this->assertDatabaseCount(ProductImage::class, 0);
    $this->assertSame([], Storage::disk('public')->allFiles());
});

test('returns 422 for invalid product prices and stock values', function (string $field, int|float $value, string $message): void {
    Storage::fake('public');

    $productData = [
        'name' => 'Desk Lamp',
        'description' => 'Adjustable reading lamp.',
        'price' => 49.99,
        'stock' => 5,
        'images' => [UploadedFile::fake()->image('desk-lamp.jpg')],
    ];
    $productData[$field] = $value;

    $this->post('/api/v1/admin/products', $productData, [
        ...storeProductManagerHeaders(),
        'Accept' => 'application/json',
    ])
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
