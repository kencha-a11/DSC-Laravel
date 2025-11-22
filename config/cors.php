<?php

return [

    // Routes where CORS is applied
    // 'paths' => ['api/*', 'sanctum/csrf-cookie', 'debug-session', 'login', 'logout', 'user', 'dashboard/*'],
    'paths' => ['*'],

    // Allow all HTTP methods
    'allowed_methods' => ['*'],

    // Specific allowed origins (including local dev server)
    'allowed_origins' => [
        'https://dsc-vite-react.vercel.app',
        'https://dsc-vite-react-p9qv111ui-kencha-a11s-projects.vercel.app',

        // Local backend origin
        'http://127.0.0.1:8000',
        'http://127.0.0.1:5173',

        // (actual) This is what i use in local development
        'http://localhost:8000',  
        'http://localhost:5173',
    ],

    // Regex patterns for allowed origins
    'allowed_origins_patterns' => [
        '/^https:\/\/dsc-vite-react.*\.vercel\.app$/',
    ],

    // Allow all headers
    'allowed_headers' => ['*'],

    // No exposed headers
    'exposed_headers' => [],

    // No preflight caching
    'max_age' => 0,

    // Allow cookies/auth headers
    'supports_credentials' => true,

];
