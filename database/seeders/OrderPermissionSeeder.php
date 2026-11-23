<?php

namespace Database\Seeders;

use App\Enums\Auth\PermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class OrderPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::findOrCreate(PermissionEnum::ViewOrders->value, 'web');
        Permission::findOrCreate(PermissionEnum::ManageOrders->value, 'web');
    }
}
