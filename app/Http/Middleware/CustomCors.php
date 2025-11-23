<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CustomCors
{
    public function handle(Request $request, Closure $next)
    {
        // $origin = $request->header('Origin');
        
        // $allowedOrigins = [
        //     // 'http://localhost:3000',
        //     // 'http://localhost:5173',
        //     // 'http://localhost:5174',
        //     'https://dsc-vite-react.vercel.app',
        // ];
        
        // // Check if origin matches Vercel pattern
        // if ($origin && preg_match('/^https:\/\/dsc-vite-react-.*\.vercel\.app$/', $origin)) {
        //     $allowedOrigins[] = $origin;
        // }
        
        // if (in_array($origin, $allowedOrigins)) {
        //     return $next($request)
        //         ->header('Access-Control-Allow-Origin', $origin)
        //         ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        //         ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN, X-Device-Timezone')
        //         ->header('Access-Control-Allow-Credentials', 'true');
        // }
        
        return $next($request);
    }
}