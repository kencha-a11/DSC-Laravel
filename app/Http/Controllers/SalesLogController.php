<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesLog;
use Illuminate\Support\Facades\Log;
use App\Models\Sale;

class SalesLogController extends Controller
{
     public function index(Request $request)
    {
         $query = Sale::with(['user', 'saleItems.product']); // eager load items and products

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($sales);
    }
}
