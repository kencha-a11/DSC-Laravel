<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

use Illuminate\Support\Facades\Log;


// CSRF cookie route (at root level)
Route::get('/sanctum/csrf-cookie', \Laravel\Sanctum\Http\Controllers\CsrfCookieController::class.'@show');

// Auth routes - all use 'web' middleware for sessions
Route::middleware(['web'])->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
});

/*
|--------------------------------------------------------------------------
| Deployment Web Routes
|--------------------------------------------------------------------------
| Test render deployment
|--------------------------------------------------------------------------
*/

// Deployment Environment Testing
Route::get('/diagnostics', function (Request $request) {
    // ------------------------------
    // Sessions info
    // ------------------------------
    $sessionsTableExists = Schema::hasTable(config('session.table', 'sessions'));
    $sessionsCount = $sessionsTableExists
        ? DB::table(config('session.table', 'sessions'))->count()
        : 0;

    // Current session data (if using cookie driver, this reads decrypted payload)
    $currentSession = Session::all();

    // ------------------------------
    // Migration status
    // ------------------------------
    $migrations = [];
    try {
        $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();
        $allMigrations = collect(glob(database_path('migrations') . '/*.php'))
            ->map(fn($file) => pathinfo($file, PATHINFO_FILENAME))
            ->toArray();

        $pendingMigrations = array_diff($allMigrations, $ranMigrations);

        $migrations = [
            'ran' => $ranMigrations,
            'pending' => array_values($pendingMigrations),
        ];
    } catch (\Exception $e) {
        $migrations = [
            'error' => 'Could not read migrations table: ' . $e->getMessage(),
        ];
    }

    // ------------------------------
    // CSRF & cookies
    // ------------------------------
    $xsrfToken = $request->cookie('XSRF-TOKEN');
    $sessionCookieName = config('session.cookie');
    $sessionCookie = $request->cookie($sessionCookieName);

    $allCookies = $request->cookies->all();
    $origin = $request->header('Origin');
    $isSecure = $request->isSecure();

    $response = response()->json([
        'timestamp' => now()->toDateTimeString(),
        'env' => app()->environment() ?? 'not set',
        'app_url' => config('app.url') ?? 'not set',
        'php_version' => phpversion(),
        'db_connection' => config('database.default') ?? 'not set',
        'db_database' => config('database.connections.' . config('database.default') . '.database') ?? 'not set',

        // Session info
        'session_driver' => config('session.driver'),
        'session_cookie_name' => $sessionCookieName,
        'session_cookie_value_truncated' => $sessionCookie ? substr($sessionCookie, 0, 20) . '...' : 'none',
        'session_domain' => config('session.domain'),
        'session_secure' => config('session.secure'),
        'session_http_only' => config('session.http_only'),
        'session_same_site' => config('session.same_site'),
        'sessions_table_exists' => $sessionsTableExists,
        'sessions_count' => $sessionsCount,
        'current_session_payload' => $currentSession, // <-- NEW

        // Sanctum / CORS info
        'sanctum_stateful_domains' => config('sanctum.stateful', []),
        'cors_allowed_origins' => config('cors.allowed_origins', []),
        'cors_allowed_origins_patterns' => config('cors.allowed_origins_patterns', []),
        'cors_allowed_methods' => config('cors.allowed_methods', []),
        'cors_allowed_headers' => config('cors.allowed_headers', []),
        'cors_supports_credentials' => config('cors.supports_credentials', false),

        // CSRF cookies
        'csrf_cookie_value' => $xsrfToken,
        'xsrf_cookie_received' => $request->hasCookie('XSRF-TOKEN'),
        'all_cookies' => $allCookies,
        'request_origin' => $origin ?? 'none',
        'is_secure_request' => $isSecure,

        // Migration info
        'migrations' => $migrations,
    ]);

    return $response;
});


use Illuminate\Support\Str;

Route::get('/debug-session', function (Request $request) {
    // ------------------
    // Browser cookies
    // ------------------
    $cookies = $request->cookies->all();

    // ------------------
    // Headers
    // ------------------
    $headers = $request->headers->all();

    // ------------------
    // Current session
    // ------------------
    $sessionId = $request->session()->getId();
    $sessionData = $request->session()->all();

    // CSRF token from current session
    $csrfToken = $request->session()->token();

    // ------------------
    // DB session (if using database driver)
    // ------------------
    $dbSession = null;
    $dbPayload = null;
    if (config('session.driver') === 'database') {
        $dbSession = DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->first();

        if ($dbSession) {
            $dbPayload = @unserialize(base64_decode($dbSession->payload));
        }
    }

    // ------------------
    // Frontend CSRF token from header
    // ------------------
    $frontendCsrf = $request->header('x-xsrf-token');
    $csrfMatch = $frontendCsrf === $csrfToken;

    // ------------------
    // Log for inspection
    // ------------------
    Log::info('Debug Session Route', [
        'cookies' => $cookies,
        'headers' => $headers,
        'session_id' => $sessionId,
        'session_data' => $sessionData,
        'csrf_token_session' => $csrfToken,
        'csrf_token_frontend' => $frontendCsrf,
        'csrf_match' => $csrfMatch,
        'db_session' => $dbSession,
        'db_payload' => $dbPayload,
    ]);

    // ------------------
    // JSON response
    // ------------------
    return response()->json([
        'message' => $csrfMatch ? 'CSRF tokens match ✅' : 'CSRF token mismatch ❌',
        'cookies' => $cookies,
        'headers' => $headers,
        'session_id' => $sessionId,
        'session_data' => $sessionData,
        'csrf_token_session' => $csrfToken,
        'csrf_token_frontend' => $frontendCsrf,
        'csrf_match' => $csrfMatch,
        'db_session' => $dbSession,
        'db_payload' => $dbPayload,
        'tip' => $csrfMatch
            ? 'Everything is fine!'
            : 'Check if XSRF-TOKEN cookie exists and matches x-xsrf-token header. Also ensure withCredentials is true in Axios.',
    ]);
});




// Route::get('/categories', [CategoryController::class, 'index']);

// data test
// Route::get('/totalSales', [\App\Http\Controllers\DashboardController::class, 'totalSales']);
// Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'getDashboard']);
// Route::get('/users', [\App\Http\Controllers\UserController::class, 'index']);

// Route::get('/logs/time', [\App\Http\Controllers\TimeLogController::class, 'index']);
// Route::get('/logs/sales', [\App\Http\Controllers\SalesLogController::class, 'index']);

// Route::get('/products', [\App\Http\Controllers\ProductController::class, 'index']);

// Route::get('/logs/inventory', [\App\Http\Controllers\InventoryLogController::class, 'index']);
// Route::get('/users', [\App\Http\Controllers\UserController::class, 'index']);

// Route::get('/dashboard/cashier/', [\App\Http\Controllers\UserDashboardController::class, 'cashierDashboardData']);
// Route::get('/dashboard/admin', [\App\Http\Controllers\DashboardController::class, 'adminDashboardData']);




// // If you prefer to put auth routes in web.php
// Route::prefix('api')->group(function () {
//     Route::post('/login', [AuthController::class, 'login']);
//     Route::post('/logout', [AuthController::class, 'logout']);
// });

// // Your regular web routes
// Route::get('/', function () {
//     return view('welcome');
// });

// Route::get('/auth/user', [AuthController::class, 'user']);



// use App\Http\Controllers\UserController;

// Route::get('/users', [UserController::class, 'index']);
// Route::post('/users/store', [UserController::class, 'store']);
// Route::get('/users/{id}/show', [UserController::class, 'show']);
// Route::put('/users/{id}/update', [UserController::class, 'update']);
// Route::delete('/users/{id}/destroy', [UserController::class, 'destroy']);

// Route::get('/test-policy/{id}', [UserController::class, 'testPolicy']);

// use App\Http\Controllers\CategoryController;

// Route::get('/categories', [CategoryController::class, 'index']);
// Route::post('/categories/store', [CategoryController::class, 'store']);
// Route::get('/categories/{id}/show', [CategoryController::class, 'show']);
// Route::put('/categories/{id}/update', [CategoryController::class, 'update']);
// Route::delete('/categories/{id}/destroy', [CategoryController::class, 'destroy']);

// use App\Http\Controllers\ProductController;

// Route::get('/products', [ProductController::class, 'index']);
// Route::post('/products/store', [ProductController::class, 'store']);
// Route::get('/products/{id}/show', [ProductController::class, 'show']);
// Route::put('/products/{id}/update', [ProductController::class, 'update']);
// Route::delete('/products/{id}/destroy', [ProductController::class, 'destroy']);

// use App\Http\Controllers\ProductImageController;

// Route::get('/products-images', [ProductImageController::class, 'index']);
// Route::post('/products-images/store', [ProductImageController::class, 'store']);
// Route::get('/products-images/{id}/show', [ProductImageController::class, 'show']);
// Route::put('/products-images/{id}/update', [ProductImageController::class, 'update']);
// Route::delete('/products-images/{id}/destroy', [ProductImageController::class, 'destroy']);

// use App\Http\Controllers\SaleController;

// Route::get('/sales', [SaleController::class, 'index']);
// Route::post('/sales/store', [SaleController::class, 'store']);
// Route::get('/sales/{id}/show', [SaleController::class, 'show']);
// Route::put('/sales/{id}/update', [SaleController::class, 'update']);
// Route::delete('/sales/{id}/destroy', [SaleController::class, 'destroy']);

// use App\Http\Controllers\SaleItemController;

// Route::get('/sale-items', [SaleItemController::class, 'index']);
// Route::post('/sale-items/store', [SaleItemController::class, 'store']);
// Route::get('/sale-items/{id}/show', [SaleItemController::class, 'show']);
// Route::put('/sale-items/{id}/update', [SaleItemController::class, 'update']);
// Route::delete('/sale-items/{id}/destroy', [SaleItemController::class, 'destroy']);


// // Route::post('/login', [AuthController::class, 'login']);
// // Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

// Route::middleware(['auth', 'role:admin'])->group(function () {
//     Route::get('/dashboard', function () {
//         return 'Admin Dashboard';
//     });
// });

// Route::get('/product-low-stock', [ProductController::class, 'lowStockAlert']);

// Route::get('/reverb-test', function () {
//     event(
//         new \App\Events\MessageSent([
//             'message' => 'Hello world',
//         ])
//     );
// });


// // Fallback route for undefined endpoints
// Route::fallback(function () {
//     return response()->json(['message' => 'Endpoint not found.'], 404);
// });
