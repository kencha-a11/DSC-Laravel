<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;

class CategoryController extends Controller
{
    public function index()
    {
        // Fetch all categories including those with no products
        $categories = Category::with('products')->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        // Trim category name for consistency
        $request->merge(['category_name' => trim($request->category_name)]);

        // Validation if category already exists
        $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                // Case-insensitive uniqueness check
                function ($attribute, $value, $fail) {
                    $exists = Category::whereRaw(
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

    // public function update(Request $request, $id)
    // {
    //     $category = Category::findOrFail($id);

    //     $validated = $request->validate([
    //         'category_name' => "sometimes|string|max:255|unique:categories,category_name,$id",
    //         'products' => 'array',
    //         'products.*' => 'integer|exists:products,id',
    //     ]);

    //     $category->update($validated);

    //     if (isset($validated['products'])) {
    //         $category->products()->sync($validated['products']);
    //     }

    //     return response()->json($category->load('products'));
    // }

    public function destroyMultiple(Request $request)
    {
        $request->validate([
            'categories' => 'required|array|min:1',
            'categories.*' => 'string|distinct'
        ]);

        // Fetch all matching categories
        $categories = Category::whereIn('category_name', $request->categories)->get();

        if ($categories->isEmpty()) {
            return response()->json(['message' => 'No matching categories found.'], 404);
        }

        foreach ($categories as $category) {
            // Detach relationships before deletion
            $category->products()->detach();
            $category->delete();
        }

        return response()->json([
            'message' => 'Selected categories deleted successfully.',
            'deleted' => $categories->pluck('category_name')
        ]);
    }
}
