<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use App\Models\User;
use App\Models\SaleItem;
use App\Models\SalesLog;
use App\Models\TimeLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // ------------------- Helper Functions -------------------

    private function calculateChange($current, $previous)
    {
        if ($previous > 0) {
            $change = (($current - $previous) / $previous) * 100;
        } elseif ($current > 0) {
            $change = 100;
        } else {
            $change = 0;
        }
        return round($change, 2) . '%';
    }

    private function totalSales($userId = null)
    {
        $query = Sale::query();
        if ($userId) $query->where('user_id', $userId);

        $current = (clone $query)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('total_amount');

        $previous = (clone $query)
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('total_amount');

        return [
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    private function totalItemsSold($userId = null)
    {
        $query = SaleItem::query()->with('sale');
        if ($userId) $query->whereHas('sale', fn($q) => $q->where('user_id', $userId));

        $current = (clone $query)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('quantity');

        $previous = (clone $query)
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('quantity');

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    private function totalTransactions($userId = null)
    {
        $query = Sale::query();
        if ($userId) $query->where('user_id', $userId);

        $current = (clone $query)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $previous = (clone $query)
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    private function inventoryCount()
    {
        $current = Product::sum('stock_quantity');
        $previous = Product::where('created_at', '<=', now()->subMonth())->sum('stock_quantity');

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    private function activeUsers()
    {
        $active = User::whereHas('sales', function ($query) {
            $query->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month);
        })->count();

        $total = User::count();

        return [
            'active_users' => $active,
            'total_users' => $total,
        ];
    }


    private function salesTrendPerYear($year = null, $userId = null)
    {
        $year = $year ?: now()->year;
        $query = Sale::query()->whereYear('created_at', $year);
        if ($userId) $query->where('user_id', $userId);

        // SQLite safe month extraction
        $salesTrendRaw = $query
            ->selectRaw("strftime('%m', created_at) as month, SUM(total_amount) as total_sales")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return collect(range(1, 12))->map(function ($m) use ($salesTrendRaw) {
            $monthName = date('F', mktime(0, 0, 0, $m, 10));
            $sales = $salesTrendRaw->firstWhere('month', sprintf('%02d', $m))['total_sales'] ?? 0;
            return [
                'month' => $monthName,
                'total_sales' => round($sales, 2),
            ];
        });
    }

    private function topSellingProducts($limit = 10, $userId = null)
    {
        $query = SaleItem::query();

        if ($userId) {
            $query->whereHas('sale', fn($q) => $q->where('user_id', $userId));
        }

        $topProductsRaw = $query
            ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take($limit)
            ->get();

        return $topProductsRaw->map(function ($item) {
            // Get one sale item to access snapshot
            $saleItem = SaleItem::where('product_id', $item->product_id)->first();

            return [
                'product_name' => $saleItem->product?->name ?? $saleItem->snapshot_name ?? 'Deleted Product',
                'total_sold' => $item->total_sold,
            ];
        });
    }




    public function nonSellingProducts(Request $request)
    {
        $days = $request->get('days', 30);
        $perPage = $request->get('per_page', 10);

        $cutoffDate = now()->subDays($days);

        $query = Product::with(['saleItems.sale'])
            ->whereDoesntHave('saleItems.sale', function ($query) use ($cutoffDate) {
                $query->where('created_at', '>=', $cutoffDate);
            })
            ->orderBy('name');

        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function ($product) {
            return [
                'id' => $product->id,
                'product_name' => $product->name,
                'last_sold_date' => optional(
                    $product->saleItems()->latest('created_at')->first()
                )->created_at?->format('Y-m-d') ?? 'Never',
            ];
        });

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'next_page_url' => $paginated->nextPageUrl(),
                'prev_page_url' => $paginated->previousPageUrl(),
            ],
        ]);
    }

    public function lowStockAlert(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity', 'asc');

        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function ($item) {
            $status = $item->stock_quantity == 0 ? 'Out of Stock' : 'Low Stock';
            return [
                'id' => $item->id,
                'product_name' => $item->name,
                'stock' => $item->stock_quantity,
                'status' => $status,
            ];
        });

        $sorted = $paginated->getCollection()
            ->sortByDesc(fn($item) => $item['status'] === 'Out of Stock' ? 1 : 0)
            ->values();

        $paginated->setCollection($sorted);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'next_page_url' => $paginated->nextPageUrl(),
                'prev_page_url' => $paginated->previousPageUrl(),
            ],
        ]);
    }

    private function getNonSellingProducts($days = 30, $limit = 10)
    {
        $cutoffDate = now()->subDays($days);

        return Product::with('saleItems.sale')
            ->whereDoesntHave('saleItems.sale', function ($query) use ($cutoffDate) {
                $query->where('created_at', '>=', $cutoffDate);
            })
            ->orderBy('name')
            ->take($limit)
            ->get()
            ->map(fn($product) => [
                'id' => $product->id,
                'product_name' => $product->name,
                'last_sold_date' => optional(
                    $product->saleItems()->latest('created_at')->first()
                )->created_at?->format('Y-m-d') ?? 'Never',
            ]);
    }

    private function getLowStockProducts($limit = 10)
    {
        return Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity', 'asc')
            ->take($limit)
            ->get()
            ->map(function ($item) {
                $status = $item->stock_quantity == 0 ? 'Out of Stock' : 'Low Stock';
                return [
                    'id' => $item->id,
                    'product_name' => $item->name,
                    'stock' => $item->stock_quantity,
                    'status' => $status,
                ];
            })
            ->sortByDesc(fn($item) => $item['status'] === 'Out of Stock' ? 1 : 0)
            ->values();
    }




    // ------------------- Admin Dashboard -------------------

    public function adminDashboardData(Request $request)
    {
        try {
            return response()->json([
                'total_sales' => $this->totalSales(),
                'total_items_sold' => $this->totalItemsSold(),
                'total_transactions' => $this->totalTransactions(),
                'inventory_count' => $this->inventoryCount(),
                'active_users' => $this->activeUsers(),
                'sales_trend' => $this->salesTrendPerYear(),
                'non_selling_products' => $this->getNonSellingProducts(30, 10),
                'top_products' => $this->topSellingProducts(10),
                'low_stock' => $this->getLowStockProducts(10),
            ]);
        } catch (\Throwable $e) {
            Log::error('Dashboard error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    // ------------------- Cashier Dashboard -------------------

    public function cashierDashboardData(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'total_sales' => $this->totalSales($userId),
            'total_items_sold' => $this->totalItemsSold($userId),
            'total_transactions' => $this->totalTransactions($userId),
            'logged_hours' => $this->userLogs($userId),
            'all_products' => Product::all(),
            'sales_log' => SalesLog::where('user_id', $userId)->get(),
            'time_logs' => TimeLog::where('user_id', $userId)->get(),
        ]);
    }
}
