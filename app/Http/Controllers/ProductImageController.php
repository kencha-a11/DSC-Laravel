<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;


class ProductImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $images = \App\Models\ProductImage::all();

        // map each image to include a full URL
        $images->transform(function ($image) {
            $image->image_url = $image->image_path ? asset('storage/' . $image->image_path) : null;
            return $image;
        });

        return response()->json($images);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'image_path' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'is_primary' => 'sometimes|boolean',
        ]);

        $image = ProductImage::create($validated);

        return response()->json([
            'message' => 'Product Image created',
            'image' => $image,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $image = \App\Models\ProductImage::find($id);
        if ($image) {
            return response()->json($image);
        } else {
            return response()->json(['message' => 'Product Image not found'], 404);
        }
        return response()->json($image);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $image = ProductImage::find($id);

        if (!$image) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        // Use the raw value from the DB, not the accessor
        $relativePath = $image->getRawOriginal('image_path');

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
            \Illuminate\Support\Facades\Log::info('Deleted image file: ' . $relativePath);
        } else {
            \Illuminate\Support\Facades\Log::warning('File not found on disk: ' . $relativePath);
        }

        $image->delete();

        return response()->json(['message' => 'Product image deleted successfully']);
    }
}
