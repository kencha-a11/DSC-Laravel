<?php

use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and assigned to the "api"
| middleware group. Build your API here!
|
*/

// Route::get('/test', function () {
//     return response()->json([
//         'message' => 'Hello from Laravel API 🚀'
//     ], 200, [], JSON_UNESCAPED_UNICODE);
// });

// Route::get('/index', [TestController::class, 'index']);

Route::get('/test', [TestController::class, 'index']);

Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);

Route::get('/users/policy/{id}', [UserController::class, 'testPolicy']);

Route::get('/products', [\App\Http\Controllers\ProductController::class, 'index']);
Route::post('/products/store', [\App\Http\Controllers\ProductController::class, 'store']);
Route::get('/products/{id}', [\App\Http\Controllers\ProductController::class, 'show']);
Route::delete('/products/{id}', [\App\Http\Controllers\ProductController::class, 'destroy']);

Route::get('/products-images', [\App\Http\Controllers\ProductImageController::class, 'index']);
Route::post('/products-images/store', [\App\Http\Controllers\ProductImageController::class, 'store']);
Route::delete('/products-images/{id}', [\App\Http\Controllers\ProductImageController::class, 'destroy']);

Route::apiResource('users', \App\Http\Controllers\UserController::class);
Route::apiResource('products', \App\Http\Controllers\ProductController::class);


use App\Http\Controllers\AuthController;
use Clue\Redis\Protocol\Model\Request;
use Illuminate\Support\Facades\Log;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// // Protected routes
// Route::middleware('auth:sanctum')->group(function () {

//     // Route::get('/user', [AuthController::class, 'userProfile']);
//     // Route::post('/logout', [AuthController::class, 'logout']);
// });

// routes/api.php
Route::middleware('auth:sanctum')->get('/auth/user', function (Request $request) {
    return $request->user();
});

// Protected routes using middleware
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    // Other protected API endpoints
});


Route::fallback(function () {
    return response()->json(['message' => 'Endpoint not found.'], 404);
});
