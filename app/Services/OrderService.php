<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Jobs\SendOrderConfirmation;

class OrderService
{
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {

            $customer = Customer::firstOrCreate(
                [
                    'email' => $data['customer']['email'],
                ],
                [
                    'name' => $data['customer']['name'],
                ]
            );

            $items = collect($data['products'])
            ->groupBy('product_id')
            ->map(function ($items, $productId) {
                return [
                'product_id' => (int) $productId,
                'quantity' => $items->sum('quantity'),
                ];
            })
            ->values()
            ->all();

            $productIds = collect($items)
            ->pluck('product_id');

            /*
             * Lock the selected product rows until the transaction
             * completes. This prevents concurrent orders from
             * overselling the same stock.
             */
            $products = Product::whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $tax = 0;
            $orderItems = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];

                if (!$product) {
                    throw ValidationException::withMessages([
                        'products' => 'One or more selected products do not exist.',
                    ]);
                }

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'products' => [
                            "Insufficient stock for product: {$product->name}. "
                            . "Available stock: {$product->stock}. "
                            . "Requested: {$quantity}.",
                        ],
                    ]);
                }

                $lineSubtotal = $product->price * $quantity;
                $lineTax = $lineSubtotal * ($product->tax_percentage / 100);
                $lineTotal = $lineSubtotal + $lineTax;

                $subtotal += $lineSubtotal;
                $tax += $lineTax;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'tax_percentage' => $product->tax_percentage,
                    'subtotal' => $lineSubtotal,
                    'tax' => $lineTax,
                    'total' => $lineTotal,
                ];
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'grand_total' => $subtotal + $tax,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create([
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_percentage' => $item['tax_percentage'],
                    'subtotal' => $item['subtotal'],
                    'tax' => $item['tax'],
                    'total' => $item['total'],
                ]);

                $item['product']->decrement('stock', $item['quantity']);
            }
            $order->load([
                'customer',
                'items.product',
            ]);

            SendOrderConfirmation::dispatch($order->id)->afterCommit();

            return $order;
        });
    }
}