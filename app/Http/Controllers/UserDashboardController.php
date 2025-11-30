<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesLog;
use App\Models\Product;
use App\Models\TimeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserDashboardController extends Controller
{
    // ------------------- Constants -------------------
    private const DEFAULT_PER_PAGE = 10;
    private const HOURS_IN_SECOND = 3600;

    // ------------------- Helper Methods -------------------

    /**
     * Calculate percentage change between current and previous values
     */
    private function calculateChange(float $current, float $previous): string
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

    /**
     * Get date range for previous period comparison
     */
    private function getPreviousPeriodRange(): array
    {
        return [
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth()
        ];
    }

    // ------------------- User Metrics -------------------

    /**
     * Calculate total logged hours for authenticated user
     */
    public function userTotalHours(Request $request): float
    {
        $userId = Auth::id();
        $tz = config('app.user_timezone', config('app.timezone', 'UTC'));

        $logs = TimeLog::where('user_id', $userId)
            ->whereNotNull('start_time')
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        $ranges = [];
        foreach ($logs as $log) {
            $start = Carbon::parse($log->start_time)->timezone($tz);
            $end = $log->end_time
                ? Carbon::parse($log->end_time)->timezone($tz)
                : Carbon::now($tz);

            if ($end->lt($start)) continue; // skip invalid ranges

            if (!empty($ranges)) {
                $lastKey = array_key_last($ranges);
                $last = $ranges[$lastKey];

                // merge overlapping logs
                if ($start->lte($last['end'])) {
                    $ranges[$lastKey]['end'] = $end->gt($last['end']) ? $end : $last['end'];
                    continue;
                }
            }
            $ranges[] = ['start' => $start, 'end' => $end];
        }

        // total up merged durations
        $totalSeconds = 0;
        foreach ($ranges as $r) {
            $totalSeconds += $r['end']->diffInSeconds($r['start']);
        }

        return round($totalSeconds / 3600, 2);
    }






    /**
     * Get total sales with comparison to previous month
     */
    private function totalSales(): array
    {
        $userId = Auth::id();

        // Current total (all time for this user)
        $current = Sale::where('user_id', $userId)
            ->sum('total_amount');

        // Previous month total
        $previous = Sale::where('user_id', $userId)
            ->whereBetween('created_at', $this->getPreviousPeriodRange())
            ->sum('total_amount');

        return [
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    /**
     * Get total items sold with comparison to previous month
     */
    private function totalItemsSold(): array
    {
        $userId = Auth::id();

        // Current total (all time)
        $current = SaleItem::whereHas('sale', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->sum('quantity');

        // Previous month total
        $previous = SaleItem::whereHas('sale', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->whereBetween('created_at', $this->getPreviousPeriodRange());
        })->sum('quantity');

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    /**
     * Get total transactions with comparison to previous month
     */
    private function totalTransactions(): array
    {
        $userId = Auth::id();

        // Current total (all time)
        $current = Sale::where('user_id', $userId)->count();

        // Previous month total
        $previous = Sale::where('user_id', $userId)
            ->whereBetween('created_at', $this->getPreviousPeriodRange())
            ->count();

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    // ------------------- Paginated Methods -------------------

    /**
     * Get paginated time logs for authenticated user
     * FIXED VERSION - Now supports date filtering like sales logs
     */
    public function userLogsPaginated(Request $request): array
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'date' => 'nullable|date',
            'timezone' => 'nullable|string',
        ]);

        $userId = Auth::id();
        if (!$userId) {
            return ['message' => 'Unauthenticated.'];
        }

        $perPage = $validated['per_page'] ?? self::DEFAULT_PER_PAGE;
        $page = $validated['page'] ?? 1;

        // ✅ Use timezone from header/middleware or fallback
        $tz = $validated['timezone'] ?? config('app.user_timezone', 'Asia/Manila');

        $query = TimeLog::where('user_id', $userId);

        if (!empty($validated['date'])) {
            $date = Carbon::parse($validated['date'], $tz)->toDateString();
            $query->whereDate('start_time', $date);
        }

        $paginated = $query
            ->orderByRaw('CASE WHEN end_time IS NULL THEN 0 ELSE 1 END')
            ->orderBy('start_time', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $logs = $paginated->getCollection()->map(function ($log) use ($tz) {
            $start = $log->start_time ? Carbon::parse($log->start_time)->timezone($tz) : null;
            $end = $log->end_time ? Carbon::parse($log->end_time)->timezone($tz) : null;

            $durationMinutes = 0;
            if ($start && $end) {
                $durationMinutes = $start->diffInMinutes($end);
            } elseif ($start) {
                $durationMinutes = $start->diffInMinutes(Carbon::now($tz));
            }

            // optional cap
            $durationMinutes = min($durationMinutes, 12 * 60);

            $formattedDuration = $durationMinutes < 1
                ? '0m'
                : ($durationMinutes < 60
                    ? "{$durationMinutes}m"
                    : sprintf("%dh %dm", intdiv($durationMinutes, 60), $durationMinutes % 60));

            return [
                'id' => $log->id,
                'date' => $start?->format('F d, Y'),
                'day' => $start?->format('l'),
                'start' => $start?->format('h:i A'),
                'end' => $end ? $end->format('h:i A') : 'Ongoing',
                'duration' => $formattedDuration,
                'status' => $log->status,
                'start_time' => $start?->toIso8601String(),
                'end_time' => $end?->toIso8601String(),
            ];
        });

        return $this->formatPaginatedResponse($paginated->setCollection($logs));
    }




    /**
     * Get paginated sales logs for authenticated user
     */
public function paginatedSalesLogs(Request $request): array
{
    // 1️⃣ Validate request inputs
    $validated = $request->validate([
        'per_page' => 'nullable|integer|min:1|max:100',
        'page' => 'nullable|integer|min:1',
        'date' => 'nullable|date',
    ]);

    $userId = Auth::id();
    if (!$userId) {
        return ['message' => 'Unauthenticated.'];
    }

    $perPage = $validated['per_page'] ?? self::DEFAULT_PER_PAGE;
    $page = $validated['page'] ?? 1;
    $tz = config('app.user_timezone', 'Asia/Manila'); // Default Manila timezone

    // 2️⃣ Build base query with eager loading
    $query = SalesLog::with(['sale.saleItems'])
        ->where('user_id', $userId);

    // 3️⃣ Filter by date if provided (using created_at)
    if (!empty($validated['date'])) {
        $date = Carbon::parse($validated['date'], $tz)->toDateString();
        $query->whereDate('created_at', $date);
    }

    // 4️⃣ Paginate results
    $paginated = $query->orderByDesc('created_at')
        ->paginate($perPage, ['*'], 'page', $page);

    // 5️⃣ Transform logs for frontend
    $logs = $paginated->getCollection()
        ->map(function ($log) use ($tz) {
            $sale = $log->sale;
            if (!$sale) return null;

            $itemsCount = $sale->saleItems->sum('quantity');
            $logTime = Carbon::parse($log->created_at)->timezone($tz);

            return [
                'id' => $log->id,
                'sale_id' => $sale->id,
                'action' => $log->action,
                'date' => $logTime->format('F d, Y'),
                'day' => $logTime->format('l'),
                'time' => $logTime->format('h:i A'),
                'start_time' => $logTime->toIso8601String(), // ISO 8601 string for frontend
                'items' => $itemsCount,
                'total' => '₱' . number_format($sale->total_amount, 2),
            ];
        })
        ->filter(); // Remove null entries if sale was deleted

    // 6️⃣ Return formatted paginated response
    return $this->formatPaginatedResponse(
        $paginated->setCollection($logs)
    );
}





    /**
     * Get paginated products with filters
     */
    public function paginatedProducts(Request $request): array
    {
        // ✅ Validate incoming request parameters
        $validated = $request->validate([
            'perPage' => 'nullable|integer|min:1|max:100', // optional, max 100 items per page
            'search' => 'nullable|string|max:255',         // optional search term
            'category' => 'nullable|string|max:255',       // optional category filter
            'status' => 'nullable|string|in:all,out of stock,low stock,stock,in stock', // optional status filter
        ]);

        // ✅ Set default per page or use provided value
        $perPage = $validated['perPage'] ?? self::DEFAULT_PER_PAGE;

        // ✅ Trim search term and assign optional filters
        $search = trim($validated['search'] ?? '');
        $category = $validated['category'] ?? null;
        $status = $validated['status'] ?? null;

        // ✅ Start query with eager loading for categories
        $query = Product::with('categories');

        // -------------------------------
        // Apply search filter if present
        // -------------------------------
        if ($search !== '') {
            $searchLower = strtolower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereHas('categories', function ($qc) use ($searchLower) {
                        $qc->whereRaw('LOWER(category_name) LIKE ?', ["%{$searchLower}%"]);
                    });
            });
            // ✅ This allows search to match either product name or category name
        }

        // -------------------------------
        // Apply category filter if present
        // -------------------------------
        if ($category && strtolower($category) !== 'all') {
            $this->applyCategoryFilter($query, $category);
            // ✅ Custom method to filter by category
        }

        // -------------------------------
        // Apply status filter if present
        // -------------------------------
        if ($status && strtolower($status) !== 'all') {
            $this->applyStatusFilter($query, $status);
            // ✅ Custom method to filter by stock status
        }

        // -------------------------------
        // Order products: out of stock first, then low stock, then in stock
        // -------------------------------
        $products = $query
            ->orderByRaw("
            CASE
                WHEN stock_quantity = 0 THEN 1
                WHEN stock_quantity <= low_stock_threshold THEN 2
                ELSE 3
            END
        ")
            ->orderBy('created_at', 'desc') // ✅ Newest products first within each stock status
            ->paginate($perPage);           // ✅ Laravel pagination returns perPage items only

        // -------------------------------
        // Transform each product for frontend
        // -------------------------------
        $products->getCollection()->transform(function ($product) {
            return $this->formatProductData($product);
            // ✅ formatProductData ensures only necessary fields are returned
        });

        // -------------------------------
        // Return paginated response
        // -------------------------------
        return $this->formatPaginatedResponse($products);
        // ✅ formatPaginatedResponse wraps data in 'data' + 'meta' structure for frontend
        // ⚠️ Important: This does NOT return all products at once; it returns only $perPage per request
        //          Infinite scroll on frontend must fetch additional pages using page param
    }

    // ------------------- Private Helper Methods -------------------


    /**
     * Format duration from minutes to "Xh Ym"
     */
    protected function formatDurationMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return "{$h}h {$m}m";
    }


    /**
     * Apply category filter to product query
     */
    private function applyCategoryFilter($query, string $category): void
    {
        $categoryLower = strtolower(trim($category));

        if ($categoryLower === 'uncategorized') {
            $query->whereDoesntHave('categories');
        } elseif (is_numeric($category)) {
            $query->whereHas('categories', fn($q) => $q->where('id', $category));
        } else {
            $query->whereHas('categories', function ($q) use ($categoryLower) {
                $q->whereRaw('LOWER(category_name) = ?', [$categoryLower]);
            });
        }
    }

    /**
     * Apply status filter to product query
     */
    private function applyStatusFilter($query, string $status): void
    {
        $statusLower = strtolower($status);

        $query->where(function ($q) use ($statusLower) {
            if ($statusLower === 'out of stock') {
                $q->where('stock_quantity', 0);
            } elseif ($statusLower === 'low stock') {
                $q->where('stock_quantity', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
            } elseif (in_array($statusLower, ['stock', 'in stock'])) {
                $q->whereColumn('stock_quantity', '>', 'low_stock_threshold');
            }
        });
    }

    /**
     * Format product data for API response
     */
private function formatProductData(Product $product): array
{
    $stock = $product->stock_quantity;
    $threshold = $product->low_stock_threshold;

    [$status, $statusColor] = match (true) {
        $stock == 0 => ['Out of Stock', 'text-red-600'],
        $stock <= $threshold => ['Low Stock', 'text-yellow-500'],
        default => ['In Stock', 'text-green-600'],
    };

    // Properly generate image URL
    $image = $product->image_path
        ? asset('storage/' . ltrim($product->image_path, '/'))
        : $this->getPlaceholderImage($product->name);

    return [
        'id' => $product->id,
        'product_name' => $product->name,
        'stock' => $stock,
        'price' => $product->price,
        'categories' => $product->categories->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->category_name
        ])->toArray(),
        'image' => $image,
        'status' => $status,
        'statusColor' => $statusColor,
        'low_stock_threshold' => $threshold,
    ];
}


    /**
     * Generate placeholder image URL
     */
    private function getPlaceholderImage(string $name): string
    {
        $initial = substr($name, 0, 1);
        return 'https://via.placeholder.com/64?text=' . urlencode($initial);
    }

    /**
     * Format paginated response consistently
     */
    private function formatPaginatedResponse($paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ];
    }

    // ------------------- API Endpoint -------------------

    /**
     * Get all cashier dashboard data
     */
    public function cashierDashboardData(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_sales' => $this->totalSales(),
                    'total_items_sold' => $this->totalItemsSold(),
                    'total_transactions' => $this->totalTransactions(),
                    'total_logged_hours' => $this->userTotalHours($request),
                    'time_logs' => $this->userLogsPaginated($request),
                    'sales_logs' => $this->paginatedSalesLogs($request),
                    'inventory' => $this->paginatedProducts($request),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
