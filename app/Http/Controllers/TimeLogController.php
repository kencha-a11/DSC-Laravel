<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimeLog;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TimeLogController extends Controller
{
    const DEFAULT_PER_PAGE = 20;
    const MAX_PER_PAGE = 100;

    /**
     * Get paginated time logs for authenticated user
     */
    public function index(Request $request)
    {
        // Optional: only require authentication if needed
        $userId = Auth::id();

        $perPage = min($request->input('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $page = max(1, (int) $request->input('page', 1));
        $deviceTimezone = $request->input('timezone', 'Asia/Manila');

        // ✅ Fetch all logs with user relationship
        $query = TimeLog::with('user');

        // 🔍 Optional filtering by specific user if provided
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // 📅 Optional date filter
        if ($request->filled('date')) {
            $query->whereDate('start_time', $request->date);
        }

        // ⚙️ Optional status filter
        if ($request->filled('status')) {
            if ($request->status === 'online') {
                $query->whereNull('end_time');
            } elseif ($request->status === 'offline') {
                $query->whereNotNull('end_time');
            }
        }

        // 🕓 Sort ongoing logs first, then by start time (latest first)
        $query->orderByRaw('CASE WHEN end_time IS NULL THEN 0 ELSE 1 END')
            ->orderBy('start_time', 'desc');

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // 🧭 Map logs with timezone conversion and full info
        $logs = $paginated->getCollection()->map(function ($log) use ($deviceTimezone) {
            $start = $log->start_time ? Carbon::parse($log->start_time)->timezone($deviceTimezone) : null;
            $end = $log->end_time ? Carbon::parse($log->end_time)->timezone($deviceTimezone) : null;

            return [
                'id' => $log->id,
                'user' => [
                    'id' => $log->user->id ?? null,
                    'name' => $log->user->name ?? 'Unknown User',
                    'email' => $log->user->email ?? null, // 👈 Include more user info if needed
                ],
                'start_time' => $start ? $start->toIso8601String() : null,
                'end_time' => $end ? $end->toIso8601String() : null,
                'status' => $log->status,
                'created_at' => $log->created_at ? $log->created_at->timezone($deviceTimezone)->toIso8601String() : null,
                'updated_at' => $log->updated_at ? $log->updated_at->timezone($deviceTimezone)->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $logs,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }



    /**
     * Create a new time log (clock-in) for authenticated user
     */
    public function store(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $timezone = $request->input('timezone', 'Asia/Manila');
        $now = Carbon::now($timezone);

        // Close any ongoing shifts and calculate duration
        $ongoingLogs = TimeLog::where('user_id', $userId)
            ->whereNull('end_time')
            ->get();

        foreach ($ongoingLogs as $log) {
            $log->end_time = $now;
            $log->status = 'logged_out';
            $log->duration = Carbon::parse($log->start_time)->diffInMinutes($now);
            $log->updated_at = $now;
            $log->save();
        }

        // Start a new shift
        $log = TimeLog::create([
            'user_id' => $userId,
            'start_time' => $now,
            'status' => 'logged_in',
            'duration' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json($log, 201);
    }
}
