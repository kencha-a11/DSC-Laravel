<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    | ✅ CRITICAL: List FRONTEND domains, not backend!
    | These domains are allowed to receive **Sanctum session cookies**.
    | Include your production frontend (Vercel) and any local dev URLs.
    */

    'stateful' => [],

    // 'stateful' => explode(',', env(
    //     'SANCTUM_STATEFUL_DOMAINS',
    //     'localhost,' .                  // Local frontend
    //     '127.0.0.1,' .                  // Local frontend
    //     '::1,' .                        // IPv6 localhost
    //     'localhost:5173,' .             // Vite dev server
    //     'localhost:5174,' .             // Vite alternate port
    //     'localhost:3000,' .             // Next.js / React dev
    //     'localhost:8000,' .             // Local backend dev (optional)
    //     '127.0.0.1:3000,' .
    //     '127.0.0.1:5173,' .
    //     '127.0.0.1:5174,' .
    //     '127.0.0.1:8000,' .
    //     'dsc-vite-react.vercel.app' .    // ✅ Your production frontend on Vercel
    //     'dsc-vite-react.netlify.app'    // ✅ Your production frontend on Netlify
    // )),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    | Specify which guards Sanctum will use. For standard Laravel apps, 'web'
    | is sufficient. This ensures cookies are tied to the correct session guard.
    */
    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    | The number of minutes until the personal access token expires.
    | 'null' means tokens do not expire by default.
    */
    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    | Optional prefix for API tokens. Useful if you want multiple token types.
    */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    | Middleware stack used by Sanctum for session authentication.
    | Ensures:
    |   - Sessions are validated
    |   - Cookies are encrypted
    |   - CSRF tokens are checked
    */
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
