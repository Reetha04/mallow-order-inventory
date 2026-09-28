<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOrder(
            $request->validated()
        );

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
    }
}