<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;

class CategoryController extends Controller
{
    /**
     * List all categories with products.
     */
    public function index()
{
    // Fetch all categories including those with no products
    $categories = Category::with('products')->get();

    return response()->json($categories);
}


    /**
     * Store a new category and attach products.
     */
    // CategoryController.php
public function store(Request $request)
{
    // Trim category name for consistency
    $request->merge(['category_name' => trim($request->category_name)]);

    // Validation
    $request->validate([
        'category_name' => [
            'required',
            'string',
            'max:255',
            // Case-insensitive uniqueness check
            function ($attribute, $value, $fail) {
                $exists = \App\Models\Category::whereRaw(
                    'LOWER(category_name) = ?', 
                    [strtolower($value)]
                )->exists();

                if ($exists) {
                    $fail('Category name "' . $value . '" already exists.');
                }
            },
        ],
        'products' => 'array',           // optional
        'products.*' => 'exists:products,id',
    ]);

    // Create the category
    $category = Category::create([
        'category_name' => $request->category_name,
    ]);

    // Associate products if any are provided
    if ($request->has('products')) {
        $category->products()->sync($request->products);
    }

    // Load products with their categories for response
    $category->load('products.categories');

    // Return the newly created category
    return response()->json($category);
}




    /**
     * Show a specific category with products.
     */
    public function show($id)
    {
        $category = Category::with('products')->findOrFail($id);
        return response()->json($category);
    }

    /**
     * Update category and its products.
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'category_name' => "sometimes|string|max:255|unique:categories,category_name,$id",
            'products' => 'array',
            'products.*' => 'integer|exists:products,id',
        ]);

        $category->update($validated);

        if (isset($validated['products'])) {
            $category->products()->sync($validated['products']);
        }

        return response()->json($category->load('products'));
    }

    /**
     * Delete a category.
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->products()->detach(); // remove pivot relationships
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
