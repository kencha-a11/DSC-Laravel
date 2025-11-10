<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\TimeLog;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Login user
     * 
     * 1️⃣ Validates credentials and optional timezone.
     * 2️⃣ Attempts login using Laravel Auth.
     * 3️⃣ Closes previous open TimeLogs.
     * 4️⃣ Starts new TimeLog for current session.
     * 5️⃣ Returns user and time log.
     * 
     * Note: CSRF is handled via Laravel Sanctum SPA middleware:
     *  - Frontend must call /sanctum/csrf-cookie first.
     *  - Axios should send X-XSRF-TOKEN header from cookie.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'timezone' => 'nullable|string',
        ]);

        $credentials = $request->only('email', 'password');
        $timezone = $request->input('timezone', 'Asia/Manila');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Prevent login if account is deactivated
            if ($user->account_status === 'deactivated') {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => ['Your account is deactivated. Please contact the administrator.'],
                ]);
            }

            // Regenerate session to prevent fixation attacks
            $request->session()->regenerate();
            $now = Carbon::now($timezone);

            // Close any previous open TimeLogs for this user
            $ongoingLogs = TimeLog::where('user_id', $user->id)
                ->whereNull('end_time')
                ->get();

            foreach ($ongoingLogs as $log) {
                $log->end_time = $now;
                $log->status = 'logged_out';
                $log->duration = Carbon::parse($log->start_time)->diffInMinutes($now);
                $log->updated_at = $now;
                $log->save();
            }

            // Start new TimeLog for this session
            $timeLog = TimeLog::create([
                'user_id' => $user->id,
                'start_time' => $now,
                'status' => 'logged_in',
                'duration' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'time_log' => $timeLog,
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    /**
     * Logout user
     * 
     * 1️⃣ Closes any open TimeLogs for this user.
     * 2️⃣ Fires Laravel logout event.
     * 3️⃣ Logs out user, invalidates session, regenerates CSRF token.
     * 4️⃣ Clears session and XSRF-TOKEN cookies.
     * 
     * Note: Always call /sanctum/csrf-cookie first to ensure CSRF is valid for SPA requests.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $timezone = $request->input('timezone', 'Asia/Manila');

        if ($user) {
            $now = Carbon::now($timezone);

            $ongoingLogs = TimeLog::where('user_id', $user->id)
                ->whereNull('end_time')
                ->get();

            foreach ($ongoingLogs as $log) {
                $log->end_time = $now;
                $log->status = 'logged_out';
                $log->duration = Carbon::parse($log->start_time)->diffInMinutes($now);
                $log->updated_at = $now;
                $log->save();
            }

            event(new \Illuminate\Auth\Events\Logout('web', $user));
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Remove cookies from browser to fully logout
        return response()
            ->json(['message' => 'Logout successful'])
            ->withCookie(cookie()->forget('laravel_session'))
            ->withCookie(cookie()->forget('XSRF-TOKEN'));
    }

    /**
     * Get currently authenticated user
     * 
     * Returns 401 if not authenticated.
     * Explicitly includes role to ensure frontend can access it.
     */
    public function user(Request $request)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Log::info('authenticated user: ', Auth::user()->toArray);

        return response()->json([
            'id' => $request->user()->id,
            'first_name' => $request->user()->first_name,
            'last_name' => $request->user()->last_name,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
        ]);
    }

    /**
     * CSRF Cookie
     * 
     * Optional: Only needed if you want a custom endpoint.
     * Laravel Sanctum SPA provides this automatically:
     * GET /sanctum/csrf-cookie
     * 
     * Example for custom use:
     * return response()->json(['message' => 'CSRF cookie set'])
     *      ->cookie('XSRF-TOKEN', csrf_token(), 0, '/', config('session.domain'), config('session.secure'), false, false, 'Strict');
     */
    // public function csrfCookie(Request $request)
    // {
    //     return response()->json(['message' => 'CSRF cookie set'])
    //         ->cookie('XSRF-TOKEN', csrf_token(), 0, '/', config('session.domain'), config('session.secure'), false, false, 'Strict');
    // }
}
