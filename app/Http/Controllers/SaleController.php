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
        Log::info('Sale request received', ['request' => $request->all()]);

        // Validate the incoming request
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
        ]);

        Log::info('Validated data', ['validated' => $validated]);

        return DB::transaction(function () use ($validated) {

            // Create the Sale once
            $sale = Sale::create([
                'user_id' => auth()->id(),
                'total_amount' => $validated['total_amount'],
            ]);

            Log::info('Sale created', ['sale' => $sale->toArray()]);

            // Loop over items and create SaleItems
            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate() // prevent race conditions
                    ->firstOrFail();

                Log::info('Processing product', [
                    'product_id' => $product->id,
                    'stock_before' => $product->stock_quantity,
                    'requested_quantity' => $item['quantity']
                ]);

                // Check stock
                if ($product->stock_quantity < $item['quantity']) {
                    Log::warning('Not enough stock', [
                        'product_id' => $product->id,
                        'available_stock' => $product->stock_quantity,
                        'requested_quantity' => $item['quantity']
                    ]);

                    return response()->json([
                        'message' => "Not enough stock for product: {$product->name}"
                    ], 422);
                }

                // Decrement stock
                $product->decrement('stock_quantity', $item['quantity']);
                Log::info('Stock decremented', [
                    'product_id' => $product->id,
                    'stock_after' => $product->stock_quantity
                ]);

                // Calculate subtotal
                $price = $product->price;
                $subtotal = $price * $item['quantity'];

                // Create SaleItem linked to the Sale
                $saleItem = $sale->saleItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                Log::info('SaleItem created', ['sale_item' => $saleItem->toArray()]);
            }

            Log::info('Sale completed', ['sale' => $sale->load('saleItems.product')->toArray()]);

            // Return sale with items and products
            return response()->json($sale->load('saleItems.product'), 201);
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
