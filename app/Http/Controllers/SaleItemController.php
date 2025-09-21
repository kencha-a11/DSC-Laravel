<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SaleItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $saleItems = \App\Models\SaleItem::all();
        return response()->json($saleItems);
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
        $saleItem = \App\Models\SaleItem::create($request->all());
        return response()->json($saleItem, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $saleItem = \App\Models\SaleItem::find($id);
        if ($saleItem) {
            return response()->json($saleItem);
        } else {
            return response()->json(['message' => 'Sale Item not found'], 404);
        }
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
        $saleItem = \App\Models\SaleItem::find($id);
        if ($saleItem) {
            $saleItem->update($request->all());
            return response()->json($saleItem);
        } else {
            return response()->json(['message' => 'Sale Item not found'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $saleItem = \App\Models\SaleItem::find($id);
        if ($saleItem) {
            $saleItem->delete();
            return response()->json(['message' => 'Sale Item deleted']);
        } else {
            return response()->json(['message' => 'Sale Item not found'], 404);
        }
    }
}
