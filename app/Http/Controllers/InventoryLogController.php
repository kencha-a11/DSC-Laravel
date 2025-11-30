<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\Log;

class InventoryLogController extends Controller
{
    public function index(Request $request)
    {
        Log::info('✅ InventoryLog index called', [
            'request' => $request->all()
        ]);

        // Base query: eager-load user and product (product may be deleted)
        $query = InventoryLog::with(['user', 'product']);

        // 🔍 Search filter (by snapshot name or action only - removed user name search)
        if ($request->filled('search')) {
            $search = trim($request->search);
            Log::info('🔎 Search filter applied', ['search' => $search]);
            
            $query->where(function ($q) use ($search) {
                $q->where('snapshot_name', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        // Filter by user
        if ($request->filled('user_id')) {
            Log::info('🎯 Filtering by user_id', ['user_id' => $request->user_id]);
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            Log::info('🎯 Filtering by action', ['action' => $request->action]);
            $query->where('action', $request->action);
        }

        // Filter by snapshot_name (product)
        if ($request->filled('snapshot_name')) {
            Log::info('🎯 Filtering by snapshot_name', ['snapshot_name' => $request->snapshot_name]);
            $query->where('snapshot_name', 'like', "%{$request->snapshot_name}%");
        }

        // ✅ Filter by date (YYYY-MM-DD)
        if ($request->filled('date')) {
            Log::info('📅 Filtering by date', ['date' => $request->date]);
            $query->whereDate('created_at', $request->date);
        }

        // Order newest first and paginate
        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        Log::info('📦 Logs fetched', [
            'count' => $logs->count(),
            'current_page' => $logs->currentPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);

        // Transform logs for frontend
        $transformedLogs = $logs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'user_name' => $log->user->name ?? 'Unknown User',
                'action' => $log->action,
                'quantity_change' => $log->quantity_change,
                'product_name' => $log->product?->name ?? $log->snapshot_name ?? 'Deleted Product',
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ];
        });

        // ✅ Return in the format your frontend expects
        return response()->json([
            'data' => $transformedLogs,
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
            ]
        ]);
    }
}