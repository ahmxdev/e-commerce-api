<?php

namespace Database\Seeders;

use App\Enums\Auth\PermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class CategoryPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::findOrCreate(PermissionEnum::ManageCategories->value, 'web');
    }
}
