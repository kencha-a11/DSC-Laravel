<?php

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
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes here will be prefixed with /api
|--------------------------------------------------------------------------
*/

// ✅ Public routes (no authentication required)
Route::post('/login', [AuthController::class, 'login']);

// ✅ Protected routes (require token authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ===========application===========

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
    Route::delete('/products/multiple', [ProductController::class, 'destroyMultiple']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::post('/products/{product}/restock', [ProductController::class, 'restock']);
    Route::post('/products/{product}/deduct', [ProductController::class, 'deduct']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::delete('/categories/multiple', [CategoryController::class, 'destroyMultiple']);

    // Logs routes
    Route::prefix('logs')->group(function () {
        Route::get('/time', [TimeLogController::class, 'index']);
        Route::get('/sales', [SalesLogController::class, 'index']);
        Route::get('/inventory', [InventoryLogController::class, 'index']);
    });
});