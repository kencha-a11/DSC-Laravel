<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * ✅ Fetch all categories (with optional pagination).
     * This version preserves your existing functionality
     * but adds pagination when the frontend requests it.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page');

        if ($perPage) {
            $categories = Category::with('products')->paginate($perPage);
        } else {
            $categories = Category::with('products')->get();
        }

        return response()->json($categories);
    }

    /**
     * ✅ Create a new category.
     */
    public function store(Request $request)
    {
        $request->merge(['category_name' => trim($request->category_name)]);

        $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $exists = Category::whereRaw('LOWER(category_name) = ?', [strtolower($value)])->exists();
                    if ($exists) {
                        $fail('Category name "' . $value . '" already exists.');
                    }
                },
            ],
            'products' => 'array',
            'products.*' => 'exists:products,id',
        ]);

        $category = Category::create([
            'category_name' => $request->category_name,
        ]);

        if ($request->has('products')) {
            $category->products()->sync($request->products);
        }

        $category->load('products.categories');

        Log::info('Category created', ['category' => $category->toArray()]);

        return response()->json($category);
    }

    public function destroyMultiple(Request $request)
    {
        $request->validate([
            'categories' => 'required|array|min:1',
            'categories.*' => 'string|distinct',
        ]);

        $categories = Category::whereIn('category_name', $request->categories)->get();

        if ($categories->isEmpty()) {
            return response()->json(['message' => 'No matching categories found.'], 404);
        }

        DB::transaction(function () use ($categories) {
            foreach ($categories as $category) {
                $category->products()->detach();
                $category->delete();
            }
        });

        return response()->json([
            'message' => 'Selected categories deleted successfully.',
            'deleted' => $categories->pluck('category_name'),
        ]);
    }

    public function searchCategory(Request $request)
    {
        $query = trim($request->input('query', ''));
        $perPage = $request->query('per_page', 10);

        if (empty($query)) {
            return response()->json(
                Category::with('products')->paginate($perPage)
            );
        }

        $categories = Category::with('products')
            ->whereRaw('LOWER(category_name) LIKE ?', ['%' . strtolower($query) . '%'])
            ->paginate($perPage);

        if ($categories->isEmpty()) {
            $newCategory = Category::create(['category_name' => ucfirst($query)]);
            $newCategory->load('products');

            return response()->json([
                'message' => 'No matching category found. Added new category.',
                'new_category' => $newCategory,
            ], 201);
        }

        return response()->json($categories);
    }
}
