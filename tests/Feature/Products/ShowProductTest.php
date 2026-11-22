<?php

use App\Models\Product;

test('public can view an individual product', function (): void {
    $product = Product::factory()->create(['name' => 'Desk Lamp']);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', 'Desk Lamp');
});

test('returns 404 when the requested product does not exist', function (): void {
    $this->getJson('/api/v1/products/999999')
        ->assertNotFound();
});
