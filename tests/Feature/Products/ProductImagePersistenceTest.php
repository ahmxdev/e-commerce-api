<?php

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\QueryException;

test('product images are returned in position order', function (): void {
    $product = Product::factory()->create();
    $lastImage = ProductImage::factory()->for($product)->create(['position' => 2]);
    $primaryImage = ProductImage::factory()->for($product)->create(['position' => 0]);
    $middleImage = ProductImage::factory()->for($product)->create(['position' => 1]);

    expect($product->images()->get()->modelKeys())->toBe([
        $primaryImage->id,
        $middleImage->id,
        $lastImage->id,
    ]);
});

test('product image positions are unique per product', function (): void {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    ProductImage::factory()->for($product)->create(['position' => 0]);

    expect(fn () => ProductImage::factory()->for($otherProduct)->create(['position' => 0]))
        ->not->toThrow(QueryException::class);

    expect(fn () => ProductImage::factory()->for($product)->create(['position' => 0]))
        ->toThrow(QueryException::class);
});

test('deleting a product removes its image records', function (): void {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();

    $product->delete();

    $this->assertModelMissing($image);
});
