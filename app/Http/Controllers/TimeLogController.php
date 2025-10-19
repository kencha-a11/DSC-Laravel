<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimeLog;
use Illuminate\Support\Facades\Log;

class TimeLogController extends Controller
{
     public function index(Request $request)
    {
        $query = TimeLog::with('user')->orderBy('start_time', 'desc');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status: ongoing/completed
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('start_time', $request->date);
        }

        // Latest first and paginate
        $logs = $query->orderBy('start_time', 'desc')->paginate(20);

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
