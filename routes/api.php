<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\ProductController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/orders', [OrderController::class, 'store']);

Route::get('/customers/{email}/orders', [CustomerOrderController::class, 'index']);

Route::get('/products/low-stock', [ProductController::class, 'lowStock']);