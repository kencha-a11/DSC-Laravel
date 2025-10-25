<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\SaleItem;

class DashboardController extends Controller
{
    // ------------------- Helper Function -------------------
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

    // ------------------- Individual Metric Functions -------------------

    public function totalSales()
    {
        $current = Sale::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        $previous = Sale::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total_amount');

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    public function totalTransactions()
    {
        $current = Sale::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $previous = Sale::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    public function totalProducts()
    {
        $current = Product::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $previous = Product::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    public function activeUsers()
    {
        $current = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $previous = User::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => $this->calculateChange($current, $previous),
        ];
    }

    public function salesTrendPerMonth()
    {
        $salesTrendRaw = Sale::selectRaw("strftime('%m', created_at) as month, SUM(total_amount) as total_sales")
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return collect(range(1, 12))->map(function ($m) use ($salesTrendRaw) {
            $monthName = date('F', mktime(0, 0, 0, $m, 10));
            $sales = $salesTrendRaw->firstWhere('month', sprintf('%02d', $m))['total_sales'] ?? 0;
            return [
                'month' => $monthName,
                'total_sales' => $sales,
            ];
        });
    }

    public function topSellingProducts($limit = 5)
    {
        $topProductsRaw = SaleItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take($limit)
            ->get();

        return $topProductsRaw->map(function ($item) {
            $product = Product::find($item->product_id);
            return [
                'product_name' => $product ? $product->name : 'Unknown',
                'total_sold' => $item->total_sold,
            ];
        });
    }

    public function recentTransactions($limit = 5)
    {
        $recentSales = Sale::with('user', 'saleItems.product')
            ->latest()
            ->take($limit)
            ->get();

        return $recentSales->map(function ($sale) {
            return [
                'transaction_id' => $sale->id,
                'user' => optional($sale->user)->name ?? 'Unknown',
                'products' => $sale->saleItems->map(function ($item) {
                    return [
                        'product_name' => optional($item->product)->name ?? 'Unknown',
                        'quantity' => $item->quantity,
                        'subtotal' => $item->subtotal,
                    ];
                }),
                'total_amount' => $sale->total_amount,
                'date' => $sale->created_at->format('Y-m-d H:i'),
            ];
        });
    }

    public function lowStockAlert()
    {
        $products = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->get();

        $mapped = $products->map(function ($item) {
            return [
                'product_name' => $item->name,
                'stock' => $item->stock_quantity,
                'status' => $item->stock_quantity == 0 ? 'Out of Stock' : 'Low Stock',
            ];
        });

        // Sort Out of Stock first
        return $mapped->sortByDesc(function ($item) {
            return $item['status'] === 'Out of Stock' ? 1 : 0;
        })->values(); // reset keys
    }

    // ------------------- Consolidated Dashboard Endpoint -------------------
    public function getDashboard()
    {
        return response()->json([
            'total_sales' => $this->totalSales(),
            'total_transactions' => $this->totalTransactions(),
            'total_products' => $this->totalProducts(),
            'active_users' => $this->activeUsers(),
            'sales_trend' => $this->salesTrendPerMonth(),
            'top_products' => $this->topSellingProducts(),
            'recent_transactions' => $this->recentTransactions(),
            'low_stock' => $this->lowStockAlert(),
        ]);
    }
}
