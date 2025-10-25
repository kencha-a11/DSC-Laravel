<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\TimeLog;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        // Attempt to authenticate
        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // 🧩 Check account status
            if ($user->account_status === 'deactivated') {
                Auth::logout(); // ensure no session remains
                throw ValidationException::withMessages([
                    'email' => ['Your account is deactivated. Please contact the administrator.'],
                ]);
            }

            $request->session()->regenerate();

            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
            ]);
        }

        // ❌ Invalid credentials
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            Log::info("Manual logout requested for user ID: {$user->id}");

            // Fire the logout event manually to trigger your listener
            event(new \Illuminate\Auth\Events\Logout('web', $user));
        }

        // Properly log out from web guard
        Auth::guard('web')->logout();

        // Invalidate session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear Sanctum cookies
        return response()
            ->json(['message' => 'Logout successful'])
            ->withCookie(cookie()->forget('laravel_session'))
            ->withCookie(cookie()->forget('XSRF-TOKEN'));
    }

    public function user(Request $request)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        return response()->json($request->user());
    }
}
