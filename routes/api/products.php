<?php

use App\Enums\Auth\PermissionEnum;
use App\Http\Controllers\Product\AdminProductController;
use App\Http\Controllers\Product\AdminProductImageController;
use App\Http\Controllers\Product\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::middleware('auth:sanctum')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::middleware('can:'.PermissionEnum::ManageProducts->value)->group(function (): void {
            Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
            Route::patch('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
            Route::post('/products/{product}/images', [AdminProductImageController::class, 'store'])
                ->name('products.images.store');
            Route::patch('/products/{product}/images/reorder', [AdminProductImageController::class, 'reorder'])
                ->name('products.images.reorder');
            Route::delete('/products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])
                ->scopeBindings()
                ->name('products.images.destroy');
        });
    });
