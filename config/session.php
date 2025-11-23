<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    | Determines how sessions are stored.
    | ✅ 'cookie' driver is suitable for API + frontend separated apps using Sanctum.
    | Other options: "file", "database", "redis", etc.
    */
    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    | Minutes until session expires. Set via env variable.
    */
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    | Encrypt session data. Optional, mostly for security if sensitive info stored.
    */
    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    | Only used if driver='file'. Not relevant for cookie driver.
    */
    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection & Table
    |--------------------------------------------------------------------------
    | Only used if driver='database'. Not relevant for cookie driver.
    */
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    | Used for cache-backed session drivers like Redis.
    */
    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    | Cleans up old sessions for some drivers. Not relevant for cookie driver.
    */
    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    | Cookie name sent to the browser.
    */
    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug(env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    | Root path of the cookie.
    */
    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    | ✅ Must match backend domain (Render) for cross-domain auth to work.
    */
    'domain' => env('SESSION_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    | ✅ Render uses HTTPS; must set true to prevent cookies being sent insecurely.
    */
    'secure' => env('SESSION_SECURE_COOKIE', false), // true in production

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    | Prevents JavaScript access to cookies. Recommended for security.
    */
    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    | ✅ 'none' is required for cross-domain (Vercel frontend → Render backend)
    | Must use secure cookies with SameSite=None
    */
    'same_site' => env('SESSION_SAME_SITE', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    | Rarely needed. Keep false unless you require top-level site isolation.
    */
    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];
