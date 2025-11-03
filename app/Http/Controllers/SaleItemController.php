<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SaleItemController extends Controller
{

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
