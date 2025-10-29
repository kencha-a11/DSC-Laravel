<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     * Supports pagination, search, and category filtering.
     */
    public function index(Request $request)
    {
        $perPage  = (int) $request->query('perPage', 10);
        $search   = trim($request->query('search', ''));
        $category = $request->query('category', null);

        $query = Product::with('categories');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereHas('categories', function ($qc) use ($search) {
                        $qc->whereRaw('LOWER(category_name) LIKE ?', ['%' . strtolower($search) . '%']);
                    });
            });
        }

        if ($category && strtolower($category) !== 'all') {
            if (is_numeric($category)) {
                $query->whereHas('categories', fn($q) => $q->where('id', $category));
            } else {
                $query->whereHas(
                    'categories',
                    fn($q) => $q->whereRaw('LOWER(category_name) = ?', [strtolower($category)])
                );
            }
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
            // Create new product
            $product = Product::create([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'stock_quantity' => $validated['stock_quantity'],
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
                'status' => 'stock',
            ]);

            // Sync categories
            if (!empty($validated['category_ids'])) {
                $product->categories()->sync($validated['category_ids']);
            }

            // Handle image upload
            if ($request->hasFile('image_path')) {
                $file = $request->file('image_path');
                $filename = uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('images/products', $filename, 'public');
                $product->update(['image_path' => $path]);
            }

            return $product;
        });

        return response()->json([
            'message' => 'Product created successfully!',
            'product' => $product->load('categories'),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category_ids' => 'array',
            'category_ids.*' => 'exists:categories,id',
            'image_path' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        DB::transaction(function () use ($request, $validated, $product) {
            $product->update([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'stock_quantity' => $validated['stock_quantity'],
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
            ]);

            // Sync categories
            $product->categories()->sync($validated['category_ids'] ?? []);

            // Handle image upload safely
            if ($request->hasFile('image_path')) {
                // Delete old image if exists
                if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                    Storage::disk('public')->delete($product->image_path);
                }

                // Upload new image
                $file = $request->file('image_path');
                $filename = uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('images/products', $filename, 'public');

                $product->update(['image_path' => $path]);
            }
        });

        return response()->json([
            'message' => 'Product updated successfully!',
            'product' => $product->load('categories'),
        ]);
    }

    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            // Delete image if exists
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }

            // Detach categories
            $product->categories()->detach();

            // Permanently delete the product
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
                // Delete image if exists
                if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                    Storage::disk('public')->delete($product->image_path);
                }

                // Detach categories
                $product->categories()->detach();

                // Permanently delete
                $product->delete();
            }
        });

        return response()->json([
            'message' => 'Selected products deleted successfully.',
            'deleted_count' => count($productIds),
        ]);
    }
}
