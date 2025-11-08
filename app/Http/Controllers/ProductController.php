<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * ======================
     *  📦 FETCH PRODUCTS
     * ======================
     */
    public function index(Request $request)
    {
        $perPage  = (int) $request->query('perPage', 10);
        $search   = trim($request->query('search', ''));
        $category = $request->query('category', null);
        $status   = $request->query('status', null);

        $query = Product::with('categories');

        // 🔍 Search filter
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereHas('categories', function ($qc) use ($search) {
                        $qc->whereRaw('LOWER(category_name) LIKE ?', ['%' . strtolower($search) . '%']);
                    });
            });
        }

        // 🏷️ Category filter
        if ($category && strtolower($category) !== 'all') {
            $categoryLower = strtolower(trim($category));

            if ($categoryLower === 'uncategorized') {
                $query->whereDoesntHave('categories');
            } elseif (is_numeric($category)) {
                $query->whereHas('categories', fn($q) => $q->where('id', $category));
            } else {
                $query->whereHas(
                    'categories',
                    fn($q) =>
                    $q->whereRaw('LOWER(category_name) = ?', [$categoryLower])
                );
            }
        }

        // 📦 Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // 🧾 Sorting & Pagination
        $products = $query
            ->orderByRaw("
                CASE
                    WHEN stock_quantity = 0 THEN 1
                    WHEN stock_quantity <= COALESCE(low_stock_threshold, 10) THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $products->getCollection()->transform(fn($p) => $this->transformProduct($p));

        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'hasMore' => $products->hasMorePages(),
        ]);
    }

    /**
     * ======================
     *  📦 FETCH PRODUCTS TO SELL
     * ======================
     */
    public function sellIndex(Request $request)
    {
        $perPage  = (int) $request->query('perPage', 10);
        $search   = trim($request->query('search', ''));
        $category = $request->query('category', null);
        $status   = $request->query('status', null);

        $query = Product::with('categories');

        // 🔍 Search filter
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereHas('categories', function ($qc) use ($search) {
                        $qc->whereRaw('LOWER(category_name) LIKE ?', ['%' . strtolower($search) . '%']);
                    });
            });
        }

        // 🏷️ Category filter
        if ($category && strtolower($category) !== 'all') {
            $categoryLower = strtolower(trim($category));

            if ($categoryLower === 'uncategorized') {
                $query->whereDoesntHave('categories');
            } elseif (is_numeric($category)) {
                $query->whereHas('categories', fn($q) => $q->where('id', $category));
            } else {
                $query->whereHas(
                    'categories',
                    fn($q) =>
                    $q->whereRaw('LOWER(category_name) = ?', [$categoryLower])
                );
            }
        }

        // 📦 Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // 🧾 Sorting & Pagination
        $products = $query
            ->orderByRaw("
        CASE
            WHEN stock_quantity > COALESCE(low_stock_threshold, 10) THEN 1
            WHEN stock_quantity > 0 AND stock_quantity <= COALESCE(low_stock_threshold, 10) THEN 2
            ELSE 3
        END
    ")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);


        $products->getCollection()->transform(fn($p) => $this->transformProduct($p));

        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'hasMore' => $products->hasMorePages(),
        ]);
    }

    /**
     * ======================
     *  ➕ CREATE PRODUCT
     * ======================
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'image_path' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $product = DB::transaction(function () use ($request, $validated) {
            $product = Product::create([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'stock_quantity' => $validated['stock_quantity'],
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
                'status' => 'stock',
            ]);

            if (!empty($validated['category_ids'])) {
                $product->categories()->sync($validated['category_ids']);
            }

            if ($request->hasFile('image_path')) {
                $file = $request->file('image_path');
                $filename = uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('images/products', $filename, 'public');
                $product->update(['image_path' => $path]);
            }

            // ✅ Log creation with snapshot_name
            $this->logInventoryAction($product, 'created', $product->stock_quantity);

            return $product;
        });

        return response()->json([
            'message' => 'Product created successfully!',
            'product' => $product->load('categories'),
        ], 201);
    }

    /**
     * ======================
     *  ✏️ UPDATE PRODUCT
     * ======================
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'image_path' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        DB::transaction(function () use ($request, $validated, $product) {
            $product->update([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
            ]);

            $product->categories()->sync($validated['category_ids'] ?? []);

            if ($request->hasFile('image_path')) {
                if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                    Storage::disk('public')->delete($product->image_path);
                }

                $file = $request->file('image_path');
                $filename = uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('images/products', $filename, 'public');
                $product->update(['image_path' => $path]);
            }

            // ✅ Log update with snapshot_name
            $this->logInventoryAction($product, 'update', 0);
        });

        return response()->json([
            'message' => 'Product updated successfully!',
            'product' => $product->load('categories'),
        ]);
    }

    /**
     * ======================
     *  🗑 DELETE PRODUCT
     * ======================
     */
    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $snapshotName = $product->name;
            Log::info("🧾 [DELETE] Product snapshot captured", [
                'product_id' => $product->id,
                'snapshot_name' => $snapshotName,
                'stock_quantity' => $product->stock_quantity,
            ]);

            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
                Log::info("🗑 Image deleted for product ID {$product->id}");
            }

            $product->categories()->detach();
            Log::info("🔗 Detached categories for product ID {$product->id}");

            // ✅ Log deletion with snapshot preserved
            $this->logInventoryAction($product, 'deleted', -$product->stock_quantity, $snapshotName);

            $product->delete();
            Log::info("✅ Product deleted successfully", ['product_id' => $product->id]);
        });

        return response()->json(['message' => 'Product deleted successfully!']);
    }

    /**
     * ======================
     *  🗑 DELETE MULTIPLE
     * ======================
     */
    public function destroyMultiple(Request $request)
    {
        $productIds = $request->input('products', []);

        if (empty($productIds)) {
            Log::warning("⚠️ No products selected for deletion.");
            return response()->json(['message' => 'No products selected for deletion.'], 400);
        }

        DB::transaction(function () use ($productIds) {
            $products = Product::whereIn('id', $productIds)->get();

            foreach ($products as $product) {
                $snapshotName = $product->name;
                Log::info("🧾 [MULTI DELETE] Snapshot captured", [
                    'product_id' => $product->id,
                    'snapshot_name' => $snapshotName,
                    'stock_quantity' => $product->stock_quantity,
                ]);

                if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                    Storage::disk('public')->delete($product->image_path);
                    Log::info("🗑 Image deleted for product ID {$product->id}");
                }

                $product->categories()->detach();
                Log::info("🔗 Detached categories for product ID {$product->id}");

                $this->logInventoryAction($product, 'deleted', -$product->stock_quantity, $snapshotName);

                $product->delete();
                Log::info("✅ Product deleted successfully (multiple delete)", [
                    'product_id' => $product->id
                ]);
            }
        });

        return response()->json([
            'message' => 'Selected products deleted successfully.',
            'deleted_count' => count($productIds),
        ]);
    }


    /**
     * ======================
     *  🔼 RESTOCK PRODUCT
     * ======================
     */
    public function restock(Request $request, Product $product)
    {
        $validated = $request->validate(['quantity' => 'required|integer|min:1']);

        DB::transaction(function () use ($validated, $product) {
            $product->increment('stock_quantity', $validated['quantity']);
            $this->logInventoryAction($product, 'restock', $validated['quantity']);
        });

        $product->refresh();

        return response()->json([
            'message' => 'Product restocked successfully!',
            'product' => $product->load('categories'),
            'new_stock' => $product->stock_quantity,
        ]);
    }

    /**
     * ======================
     *  🔽 DEDUCT PRODUCT
     * ======================
     */
    public function deduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($product->stock_quantity < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient stock. Available: ' . $product->stock_quantity,
            ], 400);
        }

        DB::transaction(function () use ($validated, $product) {
            $product->decrement('stock_quantity', $validated['quantity']);
            $this->logInventoryAction($product, 'deducted', -$validated['quantity'], $validated['reason'] ?? null);
        });

        $product->refresh();

        return response()->json([
            'message' => 'Stock deducted successfully!',
            'product' => $product->load('categories'),
            'new_stock' => $product->stock_quantity,
        ]);
    }

    /**
     * ======================
     *  ⚙️ TRANSFORM PRODUCT
     * ======================
     */
    protected function transformProduct($product)
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'categories' => $product->categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->category_name,
            ])->toArray(),
            'price' => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'low_stock_threshold' => $product->low_stock_threshold,
            'status' => $product->status,
            'image' => $product->image_path
                ? asset('storage/' . $product->image_path)
                : 'https://via.placeholder.com/64?text=' . urlencode(substr($product->name ?? 'P', 0, 1)),
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }

    /**
     * ======================
     *  🧾 INVENTORY LOGGER
     * ======================
     */
    protected function logInventoryAction($product, $action, $quantityChange = 0, $snapshotName = null)
    {
        try {
            $log = InventoryLog::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'action' => $action,
                'quantity_change' => $quantityChange,
                'snapshot_name' => $snapshotName ?? $product->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("🧮 Inventory action logged", [
                'log_id' => $log->id,
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'action' => $action,
                'quantity_change' => $quantityChange,
                'snapshot_name' => $snapshotName ?? $product->name,
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Failed to log inventory action", [
                'error' => $e->getMessage(),
                'product_id' => $product->id,
                'action' => $action,
            ]);
        }
    }
}
