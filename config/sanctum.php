<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    | ✅ CRITICAL: List FRONTEND domains, not backend!
    | These are the domains that will receive session cookies
    */

    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,127.0.0.1,dsc-vite-react.vercel.app,localhost:5173'
        // 'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'
        // 'localhost:3000,localhost:5173,localhost:5174,127.0.0.1:3000,127.0.0.1:5173,127.0.0.1:5174'
        // 'localhost,127.0.0.1,localhost:8000,127.0.0.1:8000,localhost:3000,localhost:5173,localhost:5174,127.0.0.1:3000,127.0.0.1:5173,127.0.0.1:5174'
        // 'localhost:5173'
    )),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
