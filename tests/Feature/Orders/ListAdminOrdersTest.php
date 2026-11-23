<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('index returns 401 for a guest', function (): void {
    $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
});

test('index returns 403 for a user without view orders permission', function (): void {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('admin-orders-api')->plainTextToken];

    $this->getJson('/api/v1/admin/orders', $headers)->assertForbidden();
});

test('index returns all orders with customer summaries to a permitted user', function (): void {
    $admin = User::factory()->create();
    $firstCustomer = User::factory()->create(['name' => 'First Customer']);
    $secondCustomer = User::factory()->create(['name' => 'Second Customer']);
    $firstOrder = Order::factory()->for($firstCustomer)->create();
    $secondOrder = Order::factory()->for($secondCustomer)->create();
    $permission = Permission::findOrCreate(PermissionEnum::ViewOrders->value, 'web');
    $admin->givePermissionTo($permission);
    $headers = ['Authorization' => 'Bearer '.$admin->createToken('admin-orders-api')->plainTextToken];

    $this->getJson('/api/v1/admin/orders', $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.customer.id', $secondCustomer->id)
        ->assertJsonPath('data.0.customer.name', 'Second Customer')
        ->assertJsonPath('data.1.customer.id', $firstCustomer->id)
        ->assertJsonPath('data.1.customer.name', 'First Customer')
        ->assertJsonPath('data.1.id', $firstOrder->id)
        ->assertJsonMissingPath('data.0.customer.roles')
        ->assertJsonMissingPath('data.0.items');
});
