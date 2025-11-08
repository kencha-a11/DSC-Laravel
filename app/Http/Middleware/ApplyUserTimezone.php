<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class ApplyUserTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Get the timezone from the frontend header (React sends this)
        $timezone = $request->header('X-Device-Timezone', config('app.timezone'));

        // Store it for use later in the request lifecycle
        config(['app.user_timezone' => $timezone]);

        // Optionally, set PHP’s default timezone for Carbon and date()
        date_default_timezone_set($timezone);

        return $next($request);
    }
}
