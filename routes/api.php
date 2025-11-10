<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TimeLogController;
use App\Http\Controllers\SalesLogController;
use App\Http\Controllers\InventoryLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes here will be prefixed with /api
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ===========application===========

    // this check if user is logged in
    // Route::get('/user', [UserController::class, 'user']);

    // there are two types of dashboard cashier and manager
    Route::get('/dashboard/manager', [DashboardController::class, 'adminDashboardData']);
    Route::get('/dashboard/manager/non-selling', [DashboardController::class, 'getNonSellingProducts']);
    Route::get('/dashboard/manager/low-stock', [DashboardController::class, 'getLowStockProducts']);

    Route::get('/dashboard/cashier', [UserDashboardController::class, 'cashierDashboardData']);
    Route::get('/dashboard/cashier/inventory', [UserDashboardController::class, 'paginatedProducts']);
    Route::get('/dashboard/cashier/time-logs', [UserDashboardController::class, 'userLogsPaginated']);
    Route::get('/dashboard/cashier/sales-logs', [UserDashboardController::class, 'paginatedSalesLogs']);

    // =========== Cashier Operation ===========

    // cashier operation in sell module
    Route::get('/sells/products', [ProductController::class, 'sellIndex']);
    Route::post('/sales/store', [SaleController::class, 'store']);
    // revert changes for limited time 10 seconds - if agreed rollback data - in case customer backoutF

    // =========== Manager Operation===========

    // Accounts module
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);

    // Profile Module
    Route::get('/user/profile', [ProfileController::class, 'show']);
    Route::put('/user/profile', [ProfileController::class, 'update']);
    Route::put('/user/profile/password', [ProfileController::class, 'updatePassword']);

    // manager operation related to product module
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/sell/products', [ProductController::class, 'sellIndex']);

    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/multiple', [ProductController::class, 'destroyMultiple']); // bakit ba kailangan mauna to??? my hoisting ba
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::post('/products/{product}/restock', [ProductController::class, 'restock']);
    Route::post('/products/{product}/deduct', [ProductController::class, 'deduct']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::delete('/categories/multiple', [CategoryController::class, 'destroyMultiple']);

});

Route::middleware('auth:sanctum')->prefix('logs')->group(function () {
    Route::get('/time', [TimeLogController::class, 'index']);
    Route::get('/sales', [SalesLogController::class, 'index']);
    Route::get('/inventory', [InventoryLogController::class, 'index']);
    
    // Route::post('/time', [TimeLogController::class, 'store']);
});



// // Example of grouping protected routes
// Route::middleware('auth:sanctum')->group(function () {
//     // Fetch authenticated user
//     Route::get('/profile', [AuthController::class, 'profile']);

//     // Example of resource routes
//     // Route::apiResource('posts', \App\Http\Controllers\PostController::class);
// });


// Route::get('/test', function () {
//     return response()->json([
//         'message' => 'Hello from Laravel API 🚀'
//     ], 200, [], JSON_UNESCAPED_UNICODE);
// });

// Route::get('/index', [TestController::class, 'index']);

// Route::get('/test', [TestController::class, 'index']);

// Route::get('/users', [UserController::class, 'index']);
// Route::get('/users/{id}', [UserController::class, 'show']);
// Route::post('/users', [UserController::class, 'store']);
// Route::put('/users/{id}', [UserController::class, 'update']);
// Route::delete('/users/{id}', [UserController::class, 'destroy']);

// Route::get('/users/policy/{id}', [UserController::class, 'testPolicy']);

// Route::get('/products', [\App\Http\Controllers\ProductController::class, 'index']);
// Route::post('/products/store', [\App\Http\Controllers\ProductController::class, 'store']);
// Route::get('/products/{id}', [\App\Http\Controllers\ProductController::class, 'show']);
// Route::delete('/products/{id}', [\App\Http\Controllers\ProductController::class, 'destroy']);

// Route::get('/products-images', [\App\Http\Controllers\ProductImageController::class, 'index']);
// Route::post('/products-images/store', [\App\Http\Controllers\ProductImageController::class, 'store']);
// Route::delete('/products-images/{id}', [\App\Http\Controllers\ProductImageController::class, 'destroy']);

// Route::apiResource('users', \App\Http\Controllers\UserController::class);
// Route::apiResource('products', \App\Http\Controllers\ProductController::class);


// use App\Http\Controllers\AuthController;
// use Clue\Redis\Protocol\Model\Request;
// use Illuminate\Support\Facades\Log;

// Route::post('/login', [AuthController::class, 'login']);
// Route::post('/register', [AuthController::class, 'register']);

// // // Protected routes
// Route::middleware('auth:sanctum')->group(function () {

//     Route::get('/user', [AuthController::class, 'userProfile']);
//     Route::post('/logout', [AuthController::class, 'logout']);
// });

// // routes/api.php
// Route::middleware('auth:sanctum')->get('/auth/user', function (Request $request) {
//     return $request->user();
// });

// Protected routes using middleware
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/user', function (Request $request) {
//         return $request->user();
//     });
//     Route::post('/logout', [AuthController::class, 'logout']);
//     // Other protected API endpoints
// });


// Route::fallback(function () {
//     return response()->json(['message' => 'Endpoint not found.'], 404);
// });
