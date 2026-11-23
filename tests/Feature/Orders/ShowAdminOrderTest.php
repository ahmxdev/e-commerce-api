<?php

use App\Enums\Auth\PermissionEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('show returns 401 for a guest', function (): void {
    $order = Order::factory()->create();

    $this->getJson("/api/v1/admin/orders/{$order->id}")->assertUnauthorized();
});

test('show returns 403 for a user without view orders permission', function (): void {
    $user = User::factory()->create();
    $order = Order::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('admin-order-api')->plainTextToken];

    $this->getJson("/api/v1/admin/orders/{$order->id}", $headers)->assertForbidden();
});

test('show returns customer and item details to a permitted user', function (): void {
    $admin = User::factory()->create();
    $customer = User::factory()->create(['name' => 'Order Customer']);
    $product = Product::factory()->create();
    $order = Order::factory()->for($customer)->create();
    OrderItem::factory()->for($order)->for($product)->create([
        'product_name' => 'Saved Product Name',
        'unit_price' => '19.95',
        'quantity' => 2,
    ]);
    $permission = Permission::findOrCreate(PermissionEnum::ViewOrders->value, 'web');
    $admin->givePermissionTo($permission);
    $headers = ['Authorization' => 'Bearer '.$admin->createToken('admin-order-api')->plainTextToken];

    $this->getJson("/api/v1/admin/orders/{$order->id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.customer.name', 'Order Customer')
        ->assertJsonPath('data.customer.email', $customer->email)
        ->assertJsonPath('data.items.0.product_name', 'Saved Product Name')
        ->assertJsonPath('data.items.0.line_total', '39.90')
        ->assertJsonMissingPath('data.customer.roles')
        ->assertJsonMissingPath('data.customer.permissions');
});
