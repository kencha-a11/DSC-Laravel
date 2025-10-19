<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;

class ProductController extends Controller
{
    /**
     * Display a listing of products with categories.
     */
    public function index()
    {
        // Load all products with their many-to-many categories
        $products = Product::with('categories')->get();

        // Transform the data to hide old 'category' field and ensure 'categories' array
        $products = $products->map(function ($product) {
            $stock = $product->stock_quantity ?? 0;
            $threshold = $product->low_stock_threshold ?? 10;

            // Remove old single category field if it exists
            unset($product->category);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'image' => $product->image ?? null,
                'categories' => $product->categories, // Many-to-many relationship
                'status' => $stock === 0
                    ? 'out of stock'
                    : ($stock <= $threshold ? 'low stock' : 'stock'),
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        });

        return response()->json($products);
    }

    /**
     * Store a newly created product with categories.
     */
    public function store(Request $request)
    {
        try {
            // ✅ Validate incoming data
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'stock_quantity' => 'required|integer|min:0',
                'low_stock_threshold' => 'nullable|integer|min:0',
                'category_ids' => 'array',
                'category_ids.*' => 'exists:categories,id',
                'image_path' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            ]);

            // ✅ Create the product
            $product = Product::create([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'stock_quantity' => $validated['stock_quantity'],
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
                'status' => 'stock',
            ]);

            // ✅ Sync categories (many-to-many)
            if (!empty($validated['category_ids'])) {
                $product->categories()->sync($validated['category_ids']);
            }

            // ✅ Handle image upload (if provided)
            if ($request->hasFile('image_path')) {
                $path = $request->file('image_path')->store('images/products', 'public');

                // If your Product hasOne/hasMany relation named `images_path`
                $product->images_path()->create([
                    'image_path' => $path,
                    'is_primary' => true,
                ]);
            }

            // ✅ Return formatted response
            return response()->json([
                'message' => 'Product created successfully!',
                'product' => $product->load('categories', 'images_path'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error while saving product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

     public function update(Request $request, Product $product)
    {
        try {
            // ✅ Validate incoming data
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'stock_quantity' => 'required|integer|min:0',
                'low_stock_threshold' => 'nullable|integer|min:0',
                'category_ids' => 'array',
                'category_ids.*' => 'exists:categories,id',
                'image_path' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            ]);

            // ✅ Update product fields
            $product->update([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'stock_quantity' => $validated['stock_quantity'],
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
            ]);

            // ✅ Sync categories (many-to-many)
            if (!empty($validated['category_ids'])) {
                $product->categories()->sync($validated['category_ids']);
            } else {
                $product->categories()->sync([]);
            }

            // ✅ Handle image upload (replace old if exists)
            if ($request->hasFile('image_path')) {
                // Delete old image if exists
                if ($product->images_path()->exists()) {
                    $product->images_path()->each(function($img){
                        Storage::disk('public')->delete($img->image_path);
                        $img->delete();
                    });
                }

                $path = $request->file('image_path')->store('images/products', 'public');
                $product->images_path()->create([
                    'image_path' => $path,
                    'is_primary' => true,
                ]);
            }

            return response()->json([
                'message' => 'Product updated successfully!',
                'product' => $product->load('categories', 'images_path'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error while updating product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a product.
     */
    public function destroy(Product $product)
    {
        try {
            // Delete related images
            if ($product->images_path()->exists()) {
                $product->images_path()->each(function ($img) {
                    Storage::disk('public')->delete($img->image_path);
                    $img->delete();
                });
            }

            // Delete product itself
            $product->delete();

            return response()->json([
                'message' => 'Product deleted successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error while deleting product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
