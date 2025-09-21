<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;

Route::get('/users', [UserController::class, 'index']);
Route::post('/users/store', [UserController::class, 'store']);
Route::get('/users/{id}/show', [UserController::class, 'show']);
Route::put('/users/{id}/update', [UserController::class, 'update']);
Route::delete('/users/{id}/destroy', [UserController::class, 'destroy']);

Route::get('/test-policy/{id}', [UserController::class, 'testPolicy']);

use App\Http\Controllers\CategoryController;

Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories/store', [CategoryController::class, 'store']);
Route::get('/categories/{id}/show', [CategoryController::class, 'show']);
Route::put('/categories/{id}/update', [CategoryController::class, 'update']);
Route::delete('/categories/{id}/destroy', [CategoryController::class, 'destroy']);

use App\Http\Controllers\ProductController;

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products/store', [ProductController::class, 'store']);
Route::get('/products/{id}/show', [ProductController::class, 'show']);
Route::put('/products/{id}/update', [ProductController::class, 'update']);
Route::delete('/products/{id}/destroy', [ProductController::class, 'destroy']);

use App\Http\Controllers\ProductImageController;

Route::get('/products-images', [ProductImageController::class, 'index']);
Route::post('/products-images/store', [ProductImageController::class, 'store']);
Route::get('/products-images/{id}/show', [ProductImageController::class, 'show']);
Route::put('/products-images/{id}/update', [ProductImageController::class, 'update']);
Route::delete('/products-images/{id}/destroy', [ProductImageController::class, 'destroy']);

use App\Http\Controllers\SaleController;

Route::get('/sales', [SaleController::class, 'index']);
Route::post('/sales/store', [SaleController::class, 'store']);
Route::get('/sales/{id}/show', [SaleController::class, 'show']);
Route::put('/sales/{id}/update', [SaleController::class, 'update']);
Route::delete('/sales/{id}/destroy', [SaleController::class, 'destroy']);

use App\Http\Controllers\SaleItemController;

Route::get('/sale-items', [SaleItemController::class, 'index']);
Route::post('/sale-items/store', [SaleItemController::class, 'store']);
Route::get('/sale-items/{id}/show', [SaleItemController::class, 'show']);
Route::put('/sale-items/{id}/update', [SaleItemController::class, 'update']);
Route::delete('/sale-items/{id}/destroy', [SaleItemController::class, 'destroy']);


use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', function () {
        return 'Admin Dashboard';
    });
});

Route::get('/product-low-stock', [ProductController::class, 'lowStockAlert']);

Route::get('/reverb-test', function () {
    event(
        new \App\Events\MessageSent([
            'message' => 'Hello world',
        ])
    );
});


// Fallback route for undefined endpoints
Route::fallback(function () {
    return response()->json(['message' => 'Endpoint not found.'], 404);
});
