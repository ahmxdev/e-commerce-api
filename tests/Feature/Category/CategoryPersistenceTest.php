<?php

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('seeds the uncategorized root category through the database seeder', function (): void {
    $this->seed(CategorySeeder::class);

    $this->assertDatabaseHas(Category::class, [
        'name' => 'Uncategorized',
        'parent_id' => null,
    ]);
});

test('products belong to a category and categories expose their hierarchy and products', function (): void {
    $parentCategory = Category::factory()->create(['name' => 'Home']);
    $childCategory = Category::factory()->for($parentCategory, 'parent')->create(['name' => 'Lighting']);
    $product = Product::factory()->for($childCategory)->create();

    expect($product->category->is($childCategory))->toBeTrue()
        ->and($childCategory->parent->is($parentCategory))->toBeTrue()
        ->and($parentCategory->children->contains($childCategory))->toBeTrue()
        ->and($childCategory->products->contains($product))->toBeTrue();
});

test('requires every product to have a category in the database', function (): void {
    expect(fn () => DB::table('products')->insert([
        'name' => 'Uncategorized Product',
        'description' => 'A product without a category.',
        'price' => 10,
        'stock' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
