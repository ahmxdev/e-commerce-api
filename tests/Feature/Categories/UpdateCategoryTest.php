<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Category;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function updateCategoryManagerHeaders(): array
{
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageCategories->value, 'web');
    $user->givePermissionTo($permission);

    return [
        'Authorization' => 'Bearer '.$user->createToken('category-api')->plainTextToken,
    ];
}

test('permitted user can rename and reparent a category', function (): void {
    $oldParent = Category::factory()->create(['name' => 'Home']);
    $newParent = Category::factory()->create(['name' => 'Living Room']);
    $category = Category::factory()->for($oldParent, 'parent')->create(['name' => 'Lighting']);

    $this->patchJson("/api/v1/admin/categories/{$category->id}", [
        'name' => 'Lamps',
        'parent_id' => $newParent->id,
    ], updateCategoryManagerHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Lamps')
        ->assertJsonPath('data.parent_id', $newParent->id);

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'name' => 'Lamps',
        'parent_id' => $newParent->id,
    ]);
});

test('permitted user can move a category to the root level', function (): void {
    $parentCategory = Category::factory()->create();
    $category = Category::factory()->for($parentCategory, 'parent')->create();

    $this->patchJson("/api/v1/admin/categories/{$category->id}", [
        'parent_id' => null,
    ], updateCategoryManagerHeaders())
        ->assertOk()
        ->assertJsonPath('data.parent_id', null);

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'parent_id' => null,
    ]);
});

test('returns 422 when a category parent does not exist', function (): void {
    $category = Category::factory()->create(['name' => 'Lighting']);

    $this->patchJson("/api/v1/admin/categories/{$category->id}", [
        'parent_id' => 999,
    ], updateCategoryManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('errors.parent_id.0', 'The selected parent id is invalid.');

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'name' => 'Lighting',
        'parent_id' => null,
    ]);
});

test('returns 422 when a category is assigned to itself as parent', function (): void {
    $category = Category::factory()->create();

    $this->patchJson("/api/v1/admin/categories/{$category->id}", [
        'parent_id' => $category->id,
    ], updateCategoryManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'parent_id' => null,
    ]);
});

test('returns 422 when a category is assigned to a descendant as parent', function (): void {
    $category = Category::factory()->create();
    $childCategory = Category::factory()->for($category, 'parent')->create();
    $grandchildCategory = Category::factory()->for($childCategory, 'parent')->create();

    $this->patchJson("/api/v1/admin/categories/{$category->id}", [
        'parent_id' => $grandchildCategory->id,
    ], updateCategoryManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'parent_id' => null,
    ]);
});

test('returns 422 when a category patch contains no fields', function (): void {
    $category = Category::factory()->create(['name' => 'Lighting']);

    $this->patchJson("/api/v1/admin/categories/{$category->id}", [], updateCategoryManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category']);

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'name' => 'Lighting',
    ]);
});

test('returns 422 when a category name is not a string or exceeds 255 characters', function (mixed $name, string $message): void {
    $category = Category::factory()->create(['name' => 'Lighting']);

    $this->patchJson("/api/v1/admin/categories/{$category->id}", [
        'name' => $name,
    ], updateCategoryManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', $message);

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'name' => 'Lighting',
    ]);
})->with([
    'non-string name' => [123, 'The name field must be a string.'],
    'name longer than 255 characters' => [str_repeat('a', 256), 'The name field must not be greater than 255 characters.'],
]);

test('guest cannot update a category', function (): void {
    $category = Category::factory()->create(['name' => 'Lighting']);

    $this->patchJson("/api/v1/admin/categories/{$category->id}", ['name' => 'Lamps'])
        ->assertUnauthorized();

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'name' => 'Lighting',
    ]);
});

test('returns 403 and leaves a category unchanged for a user without permission', function (): void {
    $category = Category::factory()->create(['name' => 'Lighting']);
    $user = User::factory()->create();
    $headers = [
        'Authorization' => 'Bearer '.$user->createToken('user-token')->plainTextToken,
    ];

    $this->patchJson("/api/v1/admin/categories/{$category->id}", ['name' => 'Lamps'], $headers)
        ->assertForbidden();

    $this->assertDatabaseHas(Category::class, [
        'id' => $category->id,
        'name' => 'Lighting',
    ]);
});
