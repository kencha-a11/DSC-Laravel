<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\Product;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\Auth;
use App\Models\SalesLog;
use Illuminate\Support\Carbon;

class SaleController extends Controller
{

    public function index()
    {
        $sales = Sale::with('saleItems.product')->latest()->get();

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
            'device_datetime' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $userId = Auth::id() ?? 1;

            // ✅ Use timezone applied by middleware
            $userTimezone = config('app.user_timezone', config('app.timezone'));

            // Normalize device_datetime to Carbon instance in user timezone
            $deviceDatetime = $validated['device_datetime']
                ? Carbon::parse($validated['device_datetime'], $userTimezone)
                : Carbon::now($userTimezone);

            // Convert to UTC for storage in DB (best practice)
            $deviceDatetimeUtc = $deviceDatetime->copy()->setTimezone('UTC');

            // ✅ Create Sale
            $sale = Sale::create([
                'user_id' => $userId,
                'total_amount' => $validated['total_amount'],
                'device_datetime' => $deviceDatetimeUtc,
                'device_timezone' => $userTimezone,
            ]);

            // ✅ Create Sales Log
            SalesLog::create([
                'user_id' => $userId,
                'sale_id' => $sale->id,
                'action' => 'created',
                'device_datetime' => $deviceDatetimeUtc,
                'device_timezone' => $userTimezone,
            ]);

            // ✅ Process Sale Items
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
                    'snapshot_name' => $product->name,
                    'snapshot_quantity' => $item['quantity'],
                    'snapshot_price' => $product->price,
                ]);

                // ✅ Record inventory deduction
                InventoryLog::create([
                    'user_id' => $userId,
                    'product_id' => $product->id,
                    'action' => 'deducted',
                    'quantity_change' => -$item['quantity'],
                    'created_at' => $deviceDatetimeUtc,
                    'updated_at' => $deviceDatetimeUtc,
                ]);
            }

            return response()->json([
                'message' => 'Sale created successfully!',
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
