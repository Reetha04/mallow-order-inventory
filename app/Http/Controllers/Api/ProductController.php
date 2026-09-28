<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function lowStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threshold' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        $threshold = $validated['threshold'] ?? 5;

        $products = Product::where('stock', '<', $threshold)
            ->orderBy('stock')
            ->get();

        return response()->json([
            'threshold' => $threshold,
            'data' => $products,
        ]);
    }
}