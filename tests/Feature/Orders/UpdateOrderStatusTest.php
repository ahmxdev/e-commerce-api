<?php

use App\Enums\Auth\PermissionEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('update status returns 401 for a guest', function (): void {
    $order = Order::factory()->create();

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => OrderStatusEnum::Processing->value])
        ->assertUnauthorized();
});

test('update status returns 403 for a user without manage orders permission', function (): void {
    $user = User::factory()->create();
    $order = Order::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('update-order-status-api')->plainTextToken];

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => OrderStatusEnum::Processing->value,
    ], $headers)->assertForbidden();
});

test('update status advances an order through each allowed delivery step', function (OrderStatusEnum $currentStatus, OrderStatusEnum $nextStatus): void {
    $admin = User::factory()->create();
    $order = Order::factory()->create(['status' => $currentStatus]);
    $permission = Permission::findOrCreate(PermissionEnum::ManageOrders->value, 'web');
    $admin->givePermissionTo($permission);
    $headers = ['Authorization' => 'Bearer '.$admin->createToken('update-order-status-api')->plainTextToken];

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => $nextStatus->value,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', $nextStatus->value);

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => $nextStatus->value]);
})->with([
    'pending orders can be processed' => [OrderStatusEnum::Pending, OrderStatusEnum::Processing],
    'processing orders can be shipped' => [OrderStatusEnum::Processing, OrderStatusEnum::Shipped],
    'shipped orders can be delivered' => [OrderStatusEnum::Shipped, OrderStatusEnum::Delivered],
]);

test('update status returns 422 when pending is requested directly', function (): void {
    $admin = User::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatusEnum::Processing]);
    $permission = Permission::findOrCreate(PermissionEnum::ManageOrders->value, 'web');
    $admin->givePermissionTo($permission);
    $headers = ['Authorization' => 'Bearer '.$admin->createToken('update-order-status-api')->plainTextToken];

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => OrderStatusEnum::Pending->value,
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'The selected status is invalid.');

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatusEnum::Processing->value]);
});

test('update status returns 409 when a delivery step is skipped', function (): void {
    $admin = User::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatusEnum::Pending]);
    $permission = Permission::findOrCreate(PermissionEnum::ManageOrders->value, 'web');
    $admin->givePermissionTo($permission);
    $headers = ['Authorization' => 'Bearer '.$admin->createToken('update-order-status-api')->plainTextToken];

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => OrderStatusEnum::Delivered->value,
    ], $headers)
        ->assertConflict()
        ->assertJsonPath('message', 'The requested order status transition is not allowed.');

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatusEnum::Pending->value]);
});

test('update status cancels an order and restores its stock', function (): void {
    $admin = User::factory()->create();
    $order = Order::factory()->create(['status' => OrderStatusEnum::Processing]);
    $product = Product::factory()->create(['stock' => 4]);
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);
    $permission = Permission::findOrCreate(PermissionEnum::ManageOrders->value, 'web');
    $admin->givePermissionTo($permission);
    $headers = ['Authorization' => 'Bearer '.$admin->createToken('update-order-status-api')->plainTextToken];

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => OrderStatusEnum::Cancelled->value,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatusEnum::Cancelled->value);

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatusEnum::Cancelled->value]);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7]);
});
