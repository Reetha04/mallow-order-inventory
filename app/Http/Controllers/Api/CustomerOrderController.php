<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerOrderController extends Controller
{
    public function index(string $email): JsonResponse
    {
        $customer = Customer::where('email', $email)
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        $orders = $customer->orders()
            ->with([
                'items.product',
            ])
            ->latest()
            ->get();

        return response()->json([
            'data' => $orders,
        ]);
    }
}