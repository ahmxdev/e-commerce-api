<?php

use App\Models\Product;

test('public list returns paginated products with the latest first', function (): void {
    Product::factory()->create(['name' => 'First Product']);
    $latestProduct = Product::factory()->create(['name' => 'Latest Product']);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $latestProduct->id)
        ->assertJsonPath('meta.total', 2);
});
