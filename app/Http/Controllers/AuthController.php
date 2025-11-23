<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\TimeLog;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Login user and issue a Sanctum API token
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

    // Validate timezone to prevent Carbon errors
    if (!in_array($timezone, timezone_identifiers_list())) {
        Log::warning("Invalid timezone received: {$timezone}. Falling back to Asia/Manila.");
        $timezone = 'Asia/Manila';
    }

    try {
        $user = User::where('email', $credentials['email'])->firstOrFail();

        if (!Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->account_status === 'deactivated') {
            throw ValidationException::withMessages([
                'email' => ['Your account is deactivated. Please contact the administrator.'],
            ]);
        }

        $now = Carbon::now($timezone);

        // Close any ongoing TimeLogs safely
        try {
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
        } catch (\Exception $e) {
            Log::warning('Failed to close ongoing TimeLogs', ['error' => $e->getMessage()]);
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

        // Create API token
        $token = $user->createToken('api-token')->plainTextToken;

        // Return sanitized user info (avoid returning full model)
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        return response()->json([
            'message' => 'Login successful',
            'user' => $userData,
            'time_log' => $timeLog,
            'token' => $token,
        ]);
    } catch (\Exception $e) {
        Log::error('Login failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'email' => $credentials['email'] ?? null,
            'timezone' => $timezone,
        ]);

        return response()->json(['message' => 'Login failed due to server error'], 500);
    }
}


    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request)
{
    try {
        $user = $request->user();
        $timezone = $request->input('timezone', 'Asia/Manila');
        if (!in_array($timezone, timezone_identifiers_list())) {
            Log::warning("Invalid timezone received in logout: {$timezone}. Falling back to Asia/Manila.");
            $timezone = 'Asia/Manila';
        }

        if ($user) {
            $now = Carbon::now($timezone);

            // Close ongoing TimeLogs safely
            try {
                $ongoingLogs = TimeLog::where('user_id', $user->id)
                    ->whereNull('end_time')
                    ->get();
                foreach ($ongoingLogs as $log) {
                    $log->end_time = $now;
                    $log->status = 'logged_out';
                    $log->duration = round(Carbon::parse($log->start_time)->floatDiffInHours($now), 8);
                    $log->updated_at = $now;
                    $log->save();
                }
            } catch (\Exception $e) {
                Log::warning('Failed to close ongoing TimeLogs on logout', ['error' => $e->getMessage()]);
            }

            // Fire logout event safely
            try {
                event(new \Illuminate\Auth\Events\Logout('api', $user));
            } catch (\Exception $e) {
                Log::warning('Logout event failed', ['error' => $e->getMessage()]);
            }

            // Revoke token safely
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logout successful']);
    } catch (\Exception $e) {
        Log::error('Logout failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Attempt token revocation anyway
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout completed with warnings'], 200);
    }
}


    /**
     * Get currently authenticated user
     */
    public function user(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Log::info('Authenticated user:', $user->toArray());

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }
}
