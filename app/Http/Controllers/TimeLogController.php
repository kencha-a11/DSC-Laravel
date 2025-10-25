<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimeLog;
use Illuminate\Support\Facades\Log;

class TimeLogController extends Controller
{
    public function index(Request $request)
    {
        $query = TimeLog::with('user');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('start_time', $request->date);
        }

        // Order: online first (end_time IS NULL), then offline, then by start_time desc
        $query->orderByRaw('CASE WHEN end_time IS NULL THEN 0 ELSE 1 END')
            ->orderBy('start_time', 'desc');

        // Paginate
        $logs = $query->paginate(20);

        return response()->json($logs);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $log = TimeLog::create($data);
        return response()->json($log, 201);
    }
}
