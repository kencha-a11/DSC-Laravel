<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['categories', 'images_path'])->get();

        $products = $products->map(function ($product) {
            $stock = $product->stock_quantity ?? 0;
            $threshold = $product->low_stock_threshold ?? 10;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'image' => $product->images_path->first()?->image_path ?? null, // primary image
                'categories' => $product->categories,
                'status' => $stock === 0
                    ? 'out of stock'
                    : ($stock <= $threshold ? 'low stock' : 'stock'),
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        });

        return response()->json($products);
    }

    public function store(Request $request)
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

        DB::transaction(function () use ($request, $validated, &$product) {
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

                $product->images_path()->create([
                    'image_path' => $path,
                    'is_primary' => true,
                ]);
            }
        });

        return response()->json([
            'message' => 'Product created successfully!',
            'product' => $product->load('categories', 'images_path'),
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

            // Handle image upload (replace old primary image)
            if ($request->hasFile('image_path')) {
                // Delete old images
                $product->images_path->each(function ($img) {
                    Storage::disk('public')->delete($img->image_path);
                });
                $product->images_path()->delete();

                $file = $request->file('image_path');
                $filename = uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('images/products', $filename, 'public');

                $product->images_path()->create([
                    'image_path' => $path,
                    'is_primary' => true,
                ]);
            }
        });

        return response()->json([
            'message' => 'Product updated successfully!',
            'product' => $product->load('categories', 'images_path'),
        ]);
    }

    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $product->images_path->each(
                fn($img) =>
                Storage::disk('public')->delete($img->image_path)
            );
            $product->images_path()->delete();
            $product->delete();
        });

        return response()->json(['message' => 'Product deleted successfully!']);
    }

    public function destroyMultiple(Request $request)
    {
        $productIds = $request->input('products', []);

        if (empty($productIds)) {
            return response()->json([
                'message' => 'No products selected for deletion.'
            ], 400);
        }

        // Fetch products with relationships for proper cleanup
        $products = Product::with(['categories', 'images_path'])->whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            // Detach all category relationships
            $product->categories()->detach();

            // Optionally delete associated images if they exist
            foreach ($product->images_path as $image) {
                if (file_exists(public_path($image->path))) {
                    @unlink(public_path($image->path));
                }
                $image->delete();
            }

            // Delete the product itself
            $product->delete();
        }

        return response()->json([
            'message' => 'Selected products deleted successfully.',
            'deleted_count' => count($productIds),
        ]);
    }
}
