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

        if (Auth::attempt($request->only('email', 'password'))) {
            $request->session()->regenerate();

            return response()->json([
                'message' => 'Login successful',
                'user' => Auth::user()
            ]);
        }

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


    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'extra' => 'You can add more user-related info here'
        ]);
    }
}
