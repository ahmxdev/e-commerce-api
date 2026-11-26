<?php

use App\Models\Product;
use App\Models\ProductImage;

test('public list returns paginated products with the latest first', function (): void {
    Product::factory()->create(['name' => 'First Product']);
    $latestProduct = Product::factory()->create(['name' => 'Latest Product']);
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
        ->assertJsonCount(1, 'data.0.images')
        ->assertJsonPath('data.0.images.0.id', $primaryImage->id)
        ->assertJsonPath('data.0.images.0.is_primary', true)
        ->assertJsonPath('meta.total', 2);
});
