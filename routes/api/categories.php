<?php

use App\Enums\Auth\PermissionEnum;
use App\Http\Controllers\Category\AdminCategoryController;
use App\Http\Controllers\Category\CategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

Route::middleware('auth:sanctum')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::middleware('can:'.PermissionEnum::ManageCategories->value)->group(function (): void {
            Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
            Route::patch('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
        });
    });
