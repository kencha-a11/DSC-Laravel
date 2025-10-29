<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryLog;


class InventoryLogController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryLog::with(['user', 'product']);

        // 🔍 Search filter (by action, user name, or product name)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // 🎯 Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // 🎯 Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // 🎯 Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // 📄 Latest first + paginate
        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        // 🧩 Return the same structure React expects
        if ($logs->isEmpty()) {
            return response()->json([
                'data' => [],
                'message' => 'No matching inventory logs found.',
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => 0,
                ],
            ], 200);
        }

        return response()->json($logs);
    }
}
