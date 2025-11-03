<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\InventoryLog;


class ProductController extends Controller
{
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

        // 🏷️ Category filter (safe handling for empty/all/uncategorized)
        if ($category && strtolower($category) !== 'all') {
            $categoryLower = strtolower(trim($category));

            if ($categoryLower === 'uncategorized') {
                // Products with no categories
                $query->whereDoesntHave('categories');
            } elseif (is_numeric($category)) {
                // Category by ID
                $query->whereHas('categories', fn($q) => $q->where('id', $category));
            } else {
                // Category by name (case-insensitive)
                $query->whereHas(
                    'categories',
                    fn($q) =>
                    $q->whereRaw('LOWER(category_name) = ?', [$categoryLower])
                );
            }
        }
        // ✅ If $category is empty or "all" — show ALL including uncategorized
        // (No filter applied here)

        // 📦 Status filter
        if ($status) {
            $statusLower = strtolower($status);
            $query->where(function ($q) use ($statusLower) {
                if ($statusLower === 'out of stock') {
                    $q->where('stock_quantity', 0);
                } elseif ($statusLower === 'low stock') {
                    $q->where('stock_quantity', '>', 0)
                        ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
                } elseif ($statusLower === 'stock') {
                    $q->whereColumn('stock_quantity', '>', 'low_stock_threshold');
                }
            });
        }

        // 🧾 Sorting & Pagination
        $products = $query
            ->orderByRaw("
            CASE
                WHEN stock_quantity = 0 THEN 1
                WHEN stock_quantity <= low_stock_threshold THEN 2
                ELSE 3
            END
        ")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // 🧩 Transform data for frontend
        $products->getCollection()->transform(function ($product) {
            $categories = $product->categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->category_name,
            ])->toArray();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'categories' => $categories,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'status' => $product->status,
                'image' => $product->image_path
                    ? asset('images/products/' . $product->image_path)
                    : 'https://via.placeholder.com/64?text=' . urlencode(substr($product->name ?? 'P', 0, 1)),
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        });

        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'hasMore' => $products->hasMorePages(),
        ]);
    }

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

            // ✅ Log "created"
            $this->logInventoryAction($product, 'created', $product->stock_quantity);

            return $product;
        });

        return response()->json([
            'message' => 'Product created successfully!',
            'product' => $product->load('categories'),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        // ✅ Validate only editable fields (no stock_quantity)
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'image_path' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);


        DB::transaction(function () use ($request, $validated, $product) {
            // 🧾 Update main product fields
            $product->update([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
            ]);

            // 🗂 Sync categories
            $product->categories()->sync($validated['category_ids'] ?? []);

            // 🖼 Handle image upload (optional)
            if ($request->hasFile('image_path')) {
                // Delete old image if it exists
                if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                    Storage::disk('public')->delete($product->image_path);
                }

                // Store new image
                $file = $request->file('image_path');
                $filename = uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('images/products', $filename, 'public');

                // Update image path in DB
                $product->update(['image_path' => $path]);
            }

            // 🟪 Log generic update action
            $this->logInventoryAction($product, 'update', 0);
        });

        return response()->json([
            'message' => 'Product updated successfully!',
            'product' => $product->load('categories'),
        ]);
    }

    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }

            $product->categories()->detach();

            // ✅ Log before deletion
            $this->logInventoryAction($product, 'deleted', -$product->stock_quantity);

            $product->delete();
        });

        return response()->json([
            'message' => 'Product deleted successfully!'
        ]);
    }

    public function destroyMultiple(Request $request)
    {
        $productIds = $request->input('products', []);

        if (empty($productIds)) {
            return response()->json([
                'message' => 'No products selected for deletion.'
            ], 400);
        }

        DB::transaction(function () use ($productIds) {
            $products = Product::whereIn('id', $productIds)->get();

            foreach ($products as $product) {
                if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                    Storage::disk('public')->delete($product->image_path);
                }

                $product->categories()->detach();

                // ✅ Log deleted
                $this->logInventoryAction($product, 'deleted', -$product->stock_quantity);

                $product->delete();
            }
        });

        return response()->json([
            'message' => 'Selected products deleted successfully.',
            'deleted_count' => count($productIds),
        ]);
    }

    public function restock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($validated, $product) {
            $product->increment('stock_quantity', $validated['quantity']);
            $this->logInventoryAction($product, 'restock', $validated['quantity']);
            $product->refresh();
        });

        return response()->json([
            'message' => 'Product restocked successfully!',
            'product' => $product->load('categories'),
            'new_stock' => $product->stock_quantity,
        ]);
    }

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
            $this->logInventoryAction(
                $product,
                'deducted',
                -$validated['quantity'],
                $validated['reason'] ?? null
            );
            $product->refresh();
        });

        return response()->json([
            'message' => 'Stock deducted successfully!',
            'product' => $product->load('categories'),
            'new_stock' => $product->stock_quantity,
        ]);
    }

    protected function logInventoryAction($product, string $action, int $quantityChange = 0, ?string $reason = null): void
    {
        InventoryLog::create([
            'user_id' => auth()->id() ?? 1,
            'product_id' => $product->id,
            'action' => $action,
            'quantity_change' => $quantityChange,
            'reason' => $reason, // ✅ Add this line (if column exists)
        ]);
    }
}
