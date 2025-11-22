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
    // ------------------- Admin Dashboard Helper -------------------

    private function calculateChange($current, $previous)
    {
        Log::info("Calculating change: current={$current}, previous={$previous}");
        if ($previous > 0) {
            $change = (($current - $previous) / $previous) * 100;
        } elseif ($current > 0) {
            $change = 100;
        } else {
            $change = 0;
        }
        Log::info("Change calculated: {$change}%");
        return round($change, 2) . '%';
    }

    private function totalSales()
    {
        Log::info("Calculating total sales for all users");

        $query = Sale::query();

        // Current month sales
        $current = (clone $query)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('total_amount');

        // Previous month sales
        $previous = (clone $query)
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('total_amount');

        Log::info("Total sales calculated: current={$current}, previous={$previous}");

        return [
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    private function totalItemsSold($userId = null)
    {
        Log::info("Calculating total items sold for user_id: " . ($userId ?? 'ALL'));

        $query = SaleItem::with('sale'); // eager load sale relationship

        if ($userId) {
            $query->whereHas('sale', fn($q) => $q->where('user_id', $userId));
        }

        // Current month
        $current = (clone $query)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('quantity');

        // Previous month
        $previous = (clone $query)
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('quantity');

        Log::info("Total items sold: current={$current}, previous={$previous}");

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    private function totalTransactions($userId = null)
    {
        Log::info("Calculating total transactions for user_id: " . ($userId ?? 'ALL'));
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

        Log::info("Total transactions: current={$current}, previous={$previous}");
        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    private function inventoryCount()
    {
        Log::info("Calculating inventory count");

        $current = Product::sum('stock_quantity');

        $previous = Product::where('created_at', '<=', now()->subMonth())
            ->sum('stock_quantity');

        Log::info("Inventory count: current={$current}, previous={$previous}");

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }


    private function activeUsers()
    {
        Log::info("Calculating active users based on logged-in TimeLogs this month");

        $active = \App\Models\User::whereHas('timeLogs', function ($query) {
            $query->where('status', 'logged_in')
                ->whereYear('start_time', now()->year)
                ->whereMonth('start_time', now()->month);
        })->count();

        $total = \App\Models\User::count();

        Log::info("Active users (logged in): {$active} / Total users: {$total}");

        return [
            'active_users' => $active,
            'total_users' => $total,
        ];
    }


public function getNonSellingProducts(Request $request)
{
    // Get days filter, per_page, and page number from request with defaults
    $days = (int) $request->get('days', 30);
    $perPage = (int) $request->get('per_page', 10);
    $page = (int) $request->get('page', 1);

    Log::info("Fetching non-selling products: days={$days}, perPage={$perPage}, page={$page}");

    // Calculate cutoff date for "non-selling" filter
    $cutoffDate = now()->subDays($days);

    // Query products that do NOT have sales after cutoff date
    $query = Product::with('saleItems.sale')
        ->whereDoesntHave('saleItems.sale', fn($q) => $q->where('created_at', '>=', $cutoffDate))
        ->orderBy('name');

    // Paginate the results
    $paginated = $query->paginate($perPage, ['*'], 'page', $page);

    Log::info("Paginated non-selling products fetched: total={$paginated->total()}, current_page={$paginated->currentPage()}, last_page={$paginated->lastPage()}");

    // Map each product to include last_sold_date
    $mapped = $paginated->getCollection()->map(function ($product) {
        $lastSold = $product->saleItems
            ->sortByDesc(fn($si) => $si->sale?->created_at) // get most recent sale
            ->first()?->sale?->created_at?->format('Y-m-d') ?? 'Never'; // default to 'Never'

        Log::info("Mapped non-selling product: ID={$product->id}, Name={$product->name}, Last sold={$lastSold}");

        return [
            'id' => $product->id,
            'product_name' => $product->name,
            'last_sold_date' => $lastSold,
        ];
    });

    // Replace original collection with mapped collection (important for response)
    $paginated->setCollection($mapped);

    // Return JSON response with data and pagination meta
    return response()->json([
        'data' => $mapped, // already mapped
        'meta' => [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ],
    ]);
}


public function getLowStockProducts(Request $request)
{
    // Get per_page and page from request with defaults
    $perPage = (int) $request->get('per_page', 10);
    $page = (int) $request->get('page', 1);

    Log::info("Fetching low stock products: perPage={$perPage}, page={$page}");

    // Query products with stock <= low_stock_threshold and stock >= 0
    $query = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')
        ->where('stock_quantity', '>=', 0)
        ->orderBy('stock_quantity', 'asc');

    // Paginate the results
    $paginated = $query->paginate($perPage, ['*'], 'page', $page);

    Log::info("Paginated low stock products fetched: total={$paginated->total()}, current_page={$paginated->currentPage()}, last_page={$paginated->lastPage()}");

    // Map products to include status ('Out of Stock' or 'Low Stock')
    $mapped = $paginated->getCollection()->map(function ($item) {
        $status = $item->stock_quantity == 0 ? 'Out of Stock' : 'Low Stock';
        Log::info("Mapped low stock product: ID={$item->id}, Name={$item->name}, Stock={$item->stock_quantity}, Status={$status}");
        return [
            'id' => $item->id,
            'product_name' => $item->name,
            'stock' => $item->stock_quantity,
            'status' => $status,
        ];
    })
    // Sort Out of Stock items first
    ->sortByDesc(fn($item) => $item['status'] === 'Out of Stock' ? 1 : 0)
    ->values(); // reindex the collection

    // Replace collection with mapped/sorted version
    $paginated->setCollection($mapped);

    // Return JSON response with data and pagination meta
    return response()->json([
        'data' => $mapped,
        'meta' => [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ],
    ]);
}

/*
✅ Verification:

1. Pagination is correctly implemented using Laravel's paginate() method.
2. Both endpoints return 'data' and 'meta' with current_page, last_page, per_page, total.
3. Non-selling products correctly calculate last_sold_date.
4. Low stock products are mapped with status and sorted to show 'Out of Stock' first.
5. Logging is included to track query and mapping.
*/

private function salesTrendPerYear($year = null, $userId = null)
{
    $year = $year ?: now()->year;
    Log::info("Calculating sales trend for year: {$year}, user_id: " . ($userId ?? 'ALL'));

    $query = Sale::query()->whereYear('created_at', $year);
    if ($userId) $query->where('user_id', $userId);

    // Detect database driver
    $driver = DB::getDriverName();

    if ($driver === 'pgsql') {
        // PostgreSQL
        $salesTrendRaw = $query
            ->selectRaw("EXTRACT(MONTH FROM created_at) AS month, SUM(total_amount) AS total_sales")
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    } elseif ($driver === 'sqlite') {
        // SQLite
        $salesTrendRaw = $query
            ->selectRaw("strftime('%m', created_at) AS month, SUM(total_amount) AS total_sales")
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    } else {
        throw new \Exception("Unsupported database driver: {$driver}");
    }

    // Map 1–12 months to full month names
    $trend = collect(range(1, 12))->map(function ($m) use ($salesTrendRaw, $driver) {
        $monthName = date('F', mktime(0, 0, 0, $m, 10));
        
        // SQLite strftime returns zero-padded strings, PG returns integers
        $searchMonth = $driver === 'sqlite' ? sprintf('%02d', $m) : $m;

        $sales = $salesTrendRaw->firstWhere('month', $searchMonth)?->total_sales ?? 0;
        return [
            'month' => $monthName,
            'total_sales' => round($sales, 2),
        ];
    });

    Log::info("Sales trend calculated for 12 months");
    return $trend;
}


    private function topSellingProducts($limit = 10, $userId = null)
    {
        Log::info("Fetching top {$limit} selling products for user_id: " . ($userId ?? 'ALL'));

        $query = SaleItem::query();

        if ($userId) {
            $query->whereHas('sale', fn($q) => $q->where('user_id', $userId));
        }

        // Sum snapshot_quantity instead of quantity
        $topProductsRaw = $query
            ->select('product_id', DB::raw('SUM(snapshot_quantity) as total_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take($limit)
            ->get();

        $topProducts = $topProductsRaw->map(function ($item) {
            $saleItem = SaleItem::where('product_id', $item->product_id)->first();

            return [
                'product_name' => $saleItem->product?->name ?? $saleItem->snapshot_name ?? 'Deleted Product',
                'total_sold' => (int) $item->total_sold,
            ];
        });

        Log::info("Top products fetched, count: " . $topProducts->count());
        return $topProducts;
    }


    // ------------------- User Dashboard Helper -------------------

    // public function userTotalHours($userId)
    // {
    //     Log::info("Calculating total hours for user_id: {$userId}");
    //     $logs = TimeLog::where('user_id', $userId)->get();
    //     Log::info("Fetched TimeLogs count: " . $logs->count());

    //     $totalSeconds = 0;
    //     foreach ($logs as $log) {
    //         Log::info("Processing TimeLog ID: {$log->id}, start_time: {$log->start_time}, end_time: {$log->end_time}");
    //         if ($log->start_time && $log->end_time) {
    //             try {
    //                 $start = strtotime($log->start_time);
    //                 $end = strtotime($log->end_time);
    //                 if ($end < $start) {
    //                     Log::warning("End time is before start time for TimeLog ID: {$log->id}");
    //                     continue;
    //                 }
    //                 $totalSeconds += $end - $start;
    //             } catch (\Exception $e) {
    //                 Log::error("Error parsing TimeLog ID: {$log->id}, message: " . $e->getMessage());
    //             }
    //         } else {
    //             Log::warning("Skipping TimeLog ID: {$log->id} due to missing start_time or end_time");
    //         }
    //     }

    //     $hours = round($totalSeconds / 3600, 2);
    //     Log::info("Total hours for user_id {$userId}: {$hours}");
    //     return $hours;
    // }

    // public function userLogsPaginated(Request $request, $userId = null)
    // {
    //     $userId = $userId ?: $request->user()?->id;
    //     if (!$userId) {
    //         Log::error("userLogsPaginated called without userId");
    //         return response()->json(['error' => 'User ID missing'], 400);
    //     }

    //     $perPage = $request->get('per_page', 10);
    //     $page = $request->get('page', 1);

    //     Log::info("Fetching paginated TimeLogs for user_id: {$userId}, page: {$page}, per_page: {$perPage}");

    //     $query = TimeLog::where('user_id', $userId)->orderBy('start_time', 'desc');
    //     $paginated = $query->paginate($perPage, ['*'], 'page', $page);

    //     Log::info("Fetched paginated TimeLogs, total: {$paginated->total()}");

    //     $logs = $paginated->getCollection()->map(function ($log) {
    //         Log::info("Mapping TimeLog ID: {$log->id}");
    //         $start = $log->start_time ? Carbon::parse($log->start_time) : null;
    //         $end = $log->end_time ? Carbon::parse($log->end_time) : null;

    //         $duration = null;
    //         if ($start && $end) {
    //             $seconds = $end->diffInSeconds($start);
    //             $hours = floor($seconds / 3600);
    //             $minutes = floor(($seconds % 3600) / 60);
    //             $secondsLeft = $seconds % 60;
    //             $duration = sprintf('%02dh %02dm %02ds', $hours, $minutes, $secondsLeft);
    //         }

    //         return [
    //             'id' => $log->id,
    //             'date' => $start?->format('F d, Y'),
    //             'day' => $start?->format('l'),
    //             'start' => $start?->format('h:i A'),
    //             'end' => $end?->format('h:i A'),
    //             'duration' => $duration,
    //         ];
    //     });

    //     $paginated->setCollection($logs);

    //     Log::info("Mapped collection, items count: " . $logs->count());

    //     return [
    //         'data' => $paginated->items(),
    //         'meta' => [
    //             'current_page' => $paginated->currentPage(),
    //             'last_page' => $paginated->lastPage(),
    //             'per_page' => $paginated->perPage(),
    //             'total' => $paginated->total(),
    //             'next_page_url' => $paginated->nextPageUrl(),
    //             'prev_page_url' => $paginated->previousPageUrl(),
    //         ],
    //     ];
    // }


    // =================== Admin Dashboard ===================

    public function adminDashboardData(Request $request)
    {
        try {
            Log::info("Fetching admin dashboard data");

            return response()->json([
                'total_sales' => $this->totalSales(),
                'total_items_sold' => $this->totalItemsSold(),
                'total_transactions' => $this->totalTransactions(),
                'inventory_count' => $this->inventoryCount(),
                'active_users' => $this->activeUsers(),
                'sales_trend' => $this->salesTrendPerYear(),
                'non_selling_products' => $this->getNonSellingProducts($request),
                'top_products' => $this->topSellingProducts(10),
                'low_stock' => $this->getLowStockProducts($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('Dashboard error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function paginatedProducts(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        Log::info("Fetching paginated products, page={$page}, per_page={$perPage}");

        $paginated = Product::orderBy('name')->paginate($perPage, ['*'], 'page', $page);

        Log::info("Fetched paginated products, total={$paginated->total()}");

        $paginated->getCollection()->each(function ($item) {
            Log::info("Product ID: {$item->id}, Name: {$item->name}, Stock: {$item->stock_quantity}");
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
}
