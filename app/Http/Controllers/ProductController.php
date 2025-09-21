<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;
use App\Models\ProductImage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = \App\Models\Product::all();
        return response()->json($products);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Not applicable for an API
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'stock_quantity' => 'required|integer',
            'low_stock_threshold' => 'required|integer',
            'image_path' => 'required|file|image|max:2048',
            'is_primary' => 'sometimes|boolean',
        ]);

        // Create product
        $product = Product::create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'stock_quantity' => $validated['stock_quantity'],
            'low_stock_threshold' => $validated['low_stock_threshold'],
        ]);

        // Store image in storage/app/public/product_images
        $path = $request->file('image_path')->store('product_images', 'public');

        // Save product image
        $productImage = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => $path,
            'is_primary' => $validated['is_primary'] ?? false,
        ]);

        return response()->json([
            'message' => 'Product and image created successfully',
            'product' => $product,
            'image' => $productImage,
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with('images_path')->findOrFail($id);
        return response()->json($product);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // Not applicable for an API
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = \App\Models\Product::find($id);
        $product->update($request->all());
        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::with('images')->findOrFail($id);

        // Delete associated images from storage
        foreach ($product->images as $image) {
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        }

        // Delete the product (images table rows deleted automatically if you have ON DELETE CASCADE)
        $product->delete();

        return response()->json([
            'message' => 'Product and associated images deleted successfully'
        ]);
    }

    public function lowStockAlert()
    {
        $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->get();

        if ($lowStockProducts->isNotEmpty()) {
            return response()->json([
                'message' => 'Low stock products retrieved successfully',
                'products' => $lowStockProducts,
            ], 200);
        } else {
            return response()->json([
                'message' => 'No products with low stock',
                'products' => [],
            ], 404);
        }
        return response()->json($lowStockProducts);
    }
}
