<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategoryPermissionSeeder;
use Spatie\Permission\Models\Permission;

function storeCategoryManagerHeaders(): array
{
    $user = User::factory()->create();
    $permission = Permission::findOrCreate(PermissionEnum::ManageCategories->value, 'web');
    $user->givePermissionTo($permission);

    return [
        'Authorization' => 'Bearer '.$user->createToken('category-api')->plainTextToken,
    ];
}

test('permitted user can create a root category', function (): void {
    $this->postJson('/api/v1/admin/categories', ['name' => 'Home'], storeCategoryManagerHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Home')
        ->assertJsonPath('data.parent_id', null);

    $this->assertDatabaseHas(Category::class, [
        'name' => 'Home',
        'parent_id' => null,
    ]);
});

test('permitted user can create a child category', function (): void {
    $parentCategory = Category::factory()->create(['name' => 'Home']);

    $this->postJson('/api/v1/admin/categories', [
        'name' => 'Lighting',
        'parent_id' => $parentCategory->id,
    ], storeCategoryManagerHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Lighting')
        ->assertJsonPath('data.parent_id', $parentCategory->id);

    $this->assertDatabaseHas(Category::class, [
        'name' => 'Lighting',
        'parent_id' => $parentCategory->id,
    ]);
});

test('guest cannot create a category', function (): void {
    $this->postJson('/api/v1/admin/categories', ['name' => 'Home'])
        ->assertUnauthorized();

    $this->assertDatabaseMissing(Category::class, ['name' => 'Home']);
});

test('returns 403 when a user without permission creates a category', function (): void {
    $user = User::factory()->create();
    $headers = [
        'Authorization' => 'Bearer '.$user->createToken('user-token')->plainTextToken,
    ];

    $this->postJson('/api/v1/admin/categories', ['name' => 'Home'], $headers)
        ->assertForbidden();

    $this->assertDatabaseMissing(Category::class, ['name' => 'Home']);
});

test('returns 422 when a category name is missing', function (): void {
    $this->postJson('/api/v1/admin/categories', [], storeCategoryManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('returns 422 when a category name is not a string or exceeds 255 characters', function (mixed $name, string $message): void {
    $this->postJson('/api/v1/admin/categories', ['name' => $name], storeCategoryManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', $message);

    $this->assertDatabaseCount(Category::class, 0);
})->with([
    'non-string name' => [123, 'The name field must be a string.'],
    'name longer than 255 characters' => [str_repeat('a', 256), 'The name field must not be greater than 255 characters.'],
]);

test('returns 422 when a parent category does not exist', function (): void {
    $this->postJson('/api/v1/admin/categories', [
        'name' => 'Lighting',
        'parent_id' => 999,
    ], storeCategoryManagerHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('registers the category management permission through its seeder', function (): void {
    $this->seed(CategoryPermissionSeeder::class);

    $this->assertDatabaseHas('permissions', [
        'name' => PermissionEnum::ManageCategories->value,
        'guard_name' => 'web',
    ]);
});
