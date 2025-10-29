<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use Illuminate\Support\Facades\Log;



class SaleController extends Controller
{

    public function index()
    {
        $sales = \App\Models\Sale::all();
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $sale = Sale::create([
                'user_id' => auth()->id(),
                'total_amount' => $validated['total_amount'],
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Not enough stock for product: {$product->name}");
                }

                $product->decrement('stock_quantity', $item['quantity']);

                $sale->saleItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $product->price * $item['quantity'],
                    'snapshot_name' => $item['snapshot_name'] ?? $product->name,
                    'snapshot_quantity' => $item['quantity'],
                    'snapshot_price' => $item['snapshot_price'] ?? $product->price,
                ]);
            }

            return response()->json([
                'message' => 'Sale created successfully',
                'sale_id' => $sale->id,
            ], 201);
        });
    }



    // public function show(string $id)
    // {
    //     $sale = \App\Models\Sale::find($id);
    //     if ($sale) {
    //         return response()->json($sale);
    //     } else {
    //         return response()->json(['message' => 'Sale not found'], 404);
    //     }
    // }

    // public function update(Request $request, string $id)
    // {
    //     $sale = \App\Models\Sale::find($id);
    //     if ($sale) {
    //         $sale->update($request->all());
    //         return response()->json($sale);
    //     } else {
    //         return response()->json(['message' => 'Sale not found'], 404);
    //     }
    // }
}
