<?php

use App\Models\Category;

test('public can view categories as a nested tree', function (): void {
    $homeCategory = Category::factory()->create(['name' => 'Home']);
    $lightingCategory = Category::factory()->for($homeCategory, 'parent')->create(['name' => 'Lighting']);
    Category::factory()->for($lightingCategory, 'parent')->create(['name' => 'Wall Lights']);
    Category::factory()->for($lightingCategory, 'parent')->create(['name' => 'Table Lamps']);
    Category::factory()->for($homeCategory, 'parent')->create(['name' => 'Bedroom']);
    Category::factory()->create(['name' => 'Kitchen']);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Home')
        ->assertJsonPath('data.0.parent_id', null)
        ->assertJsonPath('data.0.children.0.name', 'Bedroom')
        ->assertJsonPath('data.0.children.1.name', 'Lighting')
        ->assertJsonPath('data.0.children.1.children.0.name', 'Table Lamps')
        ->assertJsonPath('data.0.children.1.children.1.name', 'Wall Lights')
        ->assertJsonCount(0, 'data.0.children.1.children.0.children')
        ->assertJsonPath('data.1.name', 'Kitchen');
});
