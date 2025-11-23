<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;

// ✅ Move login to API routes (no CSRF needed for token auth)
// Route for CSRF cookie (keep for debugging, not used in token auth)
// Route::get('/sanctum/csrf-cookie', \Laravel\Sanctum\Http\Controllers\CsrfCookieController::class . '@show');

/*
|--------------------------------------------------------------------------
| Deployment Web Routes
|--------------------------------------------------------------------------
| Test render deployment
|--------------------------------------------------------------------------
*/

// ------------------------------
// Diagnostics: Full backend report
// ------------------------------
Route::get('/diagnostics', function (Request $request) {

    // ------------------------------
    // Sessions info (optional, mostly for local)
    // ------------------------------
    $sessionsTableExists = Schema::hasTable(config('session.table', 'sessions'));
    $sessionsCount = $sessionsTableExists
        ? DB::table(config('session.table', 'sessions'))->count()
        : 0;
    $currentSession = $sessionsTableExists ? Session::all() : null;

    // ------------------------------
    // Migration status
    // ------------------------------
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
        $migrations = ['error' => 'Could not read migrations table: ' . $e->getMessage()];
    }

    // ------------------------------
    // Axios / frontend connectivity test
    // ------------------------------
    $axiosTest = null;
    try {
        $frontendUrl = config('app.frontend_url', 'https://dsc-vite-react.vercel.app');
        $response = Http::withHeaders([
            'Origin' => $frontendUrl,
        ])->get($frontendUrl);

        $axiosTest = [
            'status' => $response->status(),
            'headers' => $response->headers(),
        ];
    } catch (\Exception $e) {
        $axiosTest = ['error' => $e->getMessage()];
    }

    // ------------------------------
    // List all routes
    // ------------------------------
    $allRoutes = collect(Route::getRoutes())->map(function ($route) {
        return [
            'uri' => $route->uri(),
        ];
    });

    // ------------------------------
    // Return full diagnostics JSON
    // ------------------------------
    return response()->json([
        'timestamp' => now()->toDateTimeString(),
        'env' => app()->environment() ?? 'not set',
        'app_url' => config('app.url'),
        'php_version' => phpversion(),
        'db_connection' => config('database.default'),
        'db_database' => config('database.connections.' . config('database.default') . '.database'),

        // Optional session info
        'session_driver' => config('session.driver'),
        'sessions_table_exists' => $sessionsTableExists,
        'sessions_count' => $sessionsCount,
        'current_session_payload' => $currentSession,

        // Sanctum & CORS
        'sanctum_stateful_domains' => config('sanctum.stateful', []),
        'cors_allowed_origins' => config('cors.allowed_origins', []),
        'cors_allowed_origins_patterns' => config('cors.allowed_origins_patterns', []),
        'cors_allowed_methods' => config('cors.allowed_methods', []),
        'cors_allowed_headers' => config('cors.allowed_headers', []),
        'cors_supports_credentials' => config('cors.supports_credentials', false),

        // Connectivity & routes
        'migrations' => $migrations,
        'axios_test' => $axiosTest,
        'routes' => $allRoutes,
    ]);
});

Route::get('/debug-session', function (Request $request) {
    $cookies = $request->cookies->all();
    $headers = $request->headers->all();
    $sessionId = $request->session()->getId();
    $sessionData = $request->session()->all();
    $csrfToken = $request->session()->token();
    $frontendCsrf = $request->header('x-xsrf-token');
    $csrfMatch = $frontendCsrf === $csrfToken;

    return response()->json([
        'message' => $csrfMatch ? 'CSRF tokens match ✅' : 'CSRF token mismatch ❌',
        'cookies' => $cookies,
        'headers' => $headers,
        'session_id' => $sessionId,
        'session_data' => $sessionData,
        'csrf_token_session' => $csrfToken,
        'csrf_token_frontend' => $frontendCsrf,
        'csrf_match' => $csrfMatch,
        'tip' => $csrfMatch
            ? 'Everything is fine! Axios requests should work with withCredentials=true'
            : 'Check: XSRF-TOKEN cookie exists, x-xsrf-token header matches, withCredentials=true in Axios, SameSite=None, Secure=true',
    ]);
});