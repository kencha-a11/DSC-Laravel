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

            if ($user->account_status === 'deactivated') {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => ['Your account is deactivated. Please contact the administrator.'],
                ]);
            }

            $request->session()->regenerate();
            $now = Carbon::now($timezone);

            // Close previous open time logs and calculate duration
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

            // Start new shift
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

        Log::info('authenticated user: ', Auth::user());

        // Explicitly include the role (even though it's in the model)
        // to ensure it's visible in JSON responses
        return response()->json([
            'id' => $request->user()->id,
            'first_name' => $request->user()->first_name,
            'last_name' => $request->user()->last_name,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
        ]);
    }
}
