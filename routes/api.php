<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/categories.php';
    require __DIR__.'/api/products.php';
    require __DIR__.'/api/cart.php';
    require __DIR__.'/api/orders.php';
});
