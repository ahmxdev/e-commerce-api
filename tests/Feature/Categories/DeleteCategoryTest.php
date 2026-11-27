<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function deleteCategoryManagerHeaders(): array
{
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageCategories->value, 'web');
    $user->givePermissionTo($permission);

    return [
        'Authorization' => 'Bearer '.$user->createToken('category-api')->plainTextToken,
    ];
}

test('permitted user can delete an empty category', function (): void {
    $category = Category::factory()->create();

    $this->deleteJson("/api/v1/admin/categories/{$category->id}", [], deleteCategoryManagerHeaders())
        ->assertNoContent();

    $this->assertModelMissing($category);
});

test('returns 409 when deleting a category assigned to a product', function (): void {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();

    $this->deleteJson("/api/v1/admin/categories/{$category->id}", [], deleteCategoryManagerHeaders())
        ->assertConflict()
        ->assertJsonPath('message', 'The category cannot be deleted while it has products or child categories.');

    $this->assertModelExists($category);
    $this->assertModelExists($product);
});

test('returns 409 when deleting a category that has child categories', function (): void {
    $category = Category::factory()->create();
    $childCategory = Category::factory()->for($category, 'parent')->create();

    $this->deleteJson("/api/v1/admin/categories/{$category->id}", [], deleteCategoryManagerHeaders())
        ->assertConflict()
        ->assertJsonPath('message', 'The category cannot be deleted while it has products or child categories.');

    $this->assertModelExists($category);
    $this->assertModelExists($childCategory);
});

test('guest cannot delete a category', function (): void {
    $category = Category::factory()->create();

    $this->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertUnauthorized();

    $this->assertModelExists($category);
});

test('returns 403 when a user without permission deletes a category', function (): void {
    $category = Category::factory()->create();
    $user = User::factory()->create();
    $headers = [
        'Authorization' => 'Bearer '.$user->createToken('user-token')->plainTextToken,
    ];

    $this->deleteJson("/api/v1/admin/categories/{$category->id}", [], $headers)
        ->assertForbidden();

    $this->assertModelExists($category);
});
