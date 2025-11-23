<?php

return [

    // Routes where CORS is applied
    // ✅ Only these routes will accept cross-origin requests
    'paths' => [
        'api/*',                 // All API routes
        // 'sanctum/csrf-cookie',   // CSRF token route (required for Sanctum auth)
        // 'debug-session',          // Optional debug route
        // 'login',                  // Auth login
        // 'logout',                 // Auth logout
        // 'user',                   // Fetch authenticated user
        // 'dashboard/*',            // Dashboard API routes
    ],

    // Allow all HTTP methods (GET, POST, PUT, DELETE, etc.)
    'allowed_methods' => ['*'],  // ✅ Allows frontend to call any API method

    // Specific allowed origins (frontend domains + local dev)
    'allowed_origins' => [
        // Production frontend domains (Vercel)
        // 'https://dsc-vite-react.vercel.app', 
        // 'https://dsc-vite-react-p9qv111ui-kencha-a11s-projects.vercel.app',  // optional preview

        // Production frontend domains (Netlify)
        'https://dsc-vite-react.netlify.app',

        // Local backend origin (for testing via local backend URL)
        'http://127.0.0.1:8000', 
        'http://127.0.0.1:5173', 

        // Local frontend origin (Vite dev server)
        'http://localhost:8000',  
        'http://localhost:5173',
    ],

    // Regex patterns for allowed origins
    'allowed_origins_patterns' => [
        // ✅ Allow any subdomain of your Vercel frontend preview deployments
        // '/^https:\/\/dsc-vite-react.*\.vercel\.app$/',
    ],

    // Allow all headers
    'allowed_headers' => ['*'],  // ✅ Frontend can send any custom headers (like Authorization)

    // Headers that should be exposed to the browser
    'exposed_headers' => [],     // None needed for now

    // How long browsers can cache the CORS preflight response
    'max_age' => 0,              // ✅ Always validate preflight requests

    // Allow cookies/auth headers to be sent
    'supports_credentials' => false,  // ✅ Required for Sanctum cookie-based authentication

];
