<?php

use App\Models\Product;
use App\Models\ProductImage;

test('public can view an individual product', function (): void {
    $product = Product::factory()->create(['name' => 'Desk Lamp']);
    $firstImage = ProductImage::factory()->for($product)->create([
        'path' => 'products/'.$product->id.'/primary.jpg',
        'position' => 0,
    ]);
    $secondImage = ProductImage::factory()->for($product)->create([
        'path' => 'products/'.$product->id.'/secondary.jpg',
        'position' => 1,
    ]);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', 'Desk Lamp')
        ->assertJsonCount(2, 'data.images')
        ->assertJsonPath('data.images.0.id', $firstImage->id)
        ->assertJsonPath('data.images.0.position', 0)
        ->assertJsonPath('data.images.0.is_primary', true)
        ->assertJsonPath('data.images.1.id', $secondImage->id)
        ->assertJsonPath('data.images.1.position', 1)
        ->assertJsonPath('data.images.1.is_primary', false);
});

test('returns 404 when the requested product does not exist', function (): void {
    $this->getJson('/api/v1/products/999999')
        ->assertNotFound();
});
