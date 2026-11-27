<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;

test('public list returns paginated products with the latest first', function (): void {
    Product::factory()->create(['name' => 'First Product']);
    $latestProduct = Product::factory()->create(['name' => 'Latest Product']);
    $productCategory = $latestProduct->category;
    $primaryImage = ProductImage::factory()->for($latestProduct)->create([
        'path' => 'products/'.$latestProduct->id.'/primary.jpg',
        'position' => 0,
    ]);
    ProductImage::factory()->for($latestProduct)->create([
        'path' => 'products/'.$latestProduct->id.'/secondary.jpg',
        'position' => 1,
    ]);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $latestProduct->id)
        ->assertJsonPath('data.0.category.id', $productCategory->id)
        ->assertJsonPath('data.0.category.name', $productCategory->name)
        ->assertJsonCount(1, 'data.0.images')
        ->assertJsonPath('data.0.images.0.id', $primaryImage->id)
        ->assertJsonPath('data.0.images.0.is_primary', true)
        ->assertJsonPath('meta.total', 2);
});

test('category filter returns products assigned to the category and its descendants', function (): void {
    $parentCategory = Category::factory()->create(['name' => 'Home']);
    $childCategory = Category::factory()->for($parentCategory, 'parent')->create(['name' => 'Lighting']);
    $grandchildCategory = Category::factory()->for($childCategory, 'parent')->create(['name' => 'Table Lamps']);
    $unrelatedCategory = Category::factory()->create(['name' => 'Kitchen']);
    $parentProduct = Product::factory()->for($parentCategory)->create();
    $childProduct = Product::factory()->for($childCategory)->create();
    $grandchildProduct = Product::factory()->for($grandchildCategory)->create();
    Product::factory()->for($unrelatedCategory)->create();

    $response = $this->getJson("/api/v1/products?category_id={$parentCategory->id}")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);

    $returnedProductIds = collect($response->json('data'))
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($returnedProductIds)->toBe([
        $parentProduct->id,
        $childProduct->id,
        $grandchildProduct->id,
    ]);
});

test('category filter excludes products assigned to ancestors and sibling branches', function (): void {
    $parentCategory = Category::factory()->create(['name' => 'Home']);
    $selectedCategory = Category::factory()->for($parentCategory, 'parent')->create(['name' => 'Lighting']);
    $grandchildCategory = Category::factory()->for($selectedCategory, 'parent')->create(['name' => 'Table Lamps']);
    $siblingCategory = Category::factory()->for($parentCategory, 'parent')->create(['name' => 'Storage']);
    $parentProduct = Product::factory()->for($parentCategory)->create();
    $selectedProduct = Product::factory()->for($selectedCategory)->create();
    $grandchildProduct = Product::factory()->for($grandchildCategory)->create();
    Product::factory()->for($siblingCategory)->create();

    $response = $this->getJson("/api/v1/products?category_id={$selectedCategory->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);

    $returnedProductIds = collect($response->json('data'))
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($returnedProductIds)->toBe([
        $selectedProduct->id,
        $grandchildProduct->id,
    ])->not->toContain($parentProduct->id);
});

test('returns 422 when product category filter does not exist', function (): void {
    $this->getJson('/api/v1/products?category_id=999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
});
