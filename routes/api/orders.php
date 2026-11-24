<?php

use App\Enums\Auth\PermissionEnum;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderId}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::prefix('admin')
        ->name('admin.')
        ->group(function (): void {
            Route::middleware('can:'.PermissionEnum::ViewOrders->value)->group(function (): void {
                Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
                Route::get('/orders/{orderId}', [AdminOrderController::class, 'show'])->name('orders.show');
            });

            Route::middleware('can:'.PermissionEnum::ManageOrders->value)->group(function (): void {
                Route::patch('/orders/{orderId}/status', [AdminOrderController::class, 'updateStatus'])
                    ->name('orders.status.update');
            });
        });
});
