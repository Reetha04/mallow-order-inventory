<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_be_created_successfully(): void
    {
        Queue::fake();

        $customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $product = Product::factory()->create([
            'name' => 'Test Product',
            'price' => 100,
            'tax_percentage' => 10,
            'stock' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('message', 'Order created successfully.')
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.tax', '20.00')
            ->assertJsonPath('data.grand_total', '220.00');

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'subtotal' => 200,
            'tax' => 20,
            'grand_total' => 220,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'subtotal' => 200,
            'tax' => 20,
            'total' => 220,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);

        Queue::assertPushed(SendOrderConfirmation::class);
    }
    public function test_order_fails_when_stock_is_insufficient(): void
{
    $product = Product::factory()->create([
        'name' => 'Limited Stock Product',
        'price' => 100,
        'tax_percentage' => 10,
        'stock' => 2,
    ]);

    $response = $this->postJson('/api/orders', [
        'customer' => [
            'name' => 'Stock Test Customer',
            'email' => 'stocktest@example.com',
        ],
        'products' => [
            [
                'product_id' => $product->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors('products');

    $this->assertDatabaseCount('orders', 0);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 2,
    ]);
}

public function test_customer_can_view_order_history_by_email(): void
{
    $customer = Customer::factory()->create([
        'name' => 'History Customer',
        'email' => 'history@example.com',
    ]);

    $product = Product::factory()->create([
        'name' => 'History Product',
        'price' => 100,
        'tax_percentage' => 10,
        'stock' => 10,
    ]);

    $order = $customer->orders()->create([
        'subtotal' => 200,
        'tax' => 20,
        'grand_total' => 220,
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 100,
        'tax_percentage' => 10,
        'subtotal' => 200,
        'tax' => 20,
        'total' => 220,
    ]);

    $response = $this->getJson(
        '/api/customers/' . urlencode($customer->email) . '/orders'
    );

    $response
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order->id)
        ->assertJsonPath('data.0.customer_id', $customer->id)
        ->assertJsonPath('data.0.grand_total', '220.00')
        ->assertJsonPath('data.0.items.0.product_id', $product->id)
        ->assertJsonPath('data.0.items.0.quantity', 2);
}
public function test_customer_order_history_returns_404_when_customer_does_not_exist(): void
{
    $response = $this->getJson(
        '/api/customers/nonexistent@example.com/orders'
    );

    $response
        ->assertStatus(404)
        ->assertJson([
            'message' => 'Customer not found.',
        ]);
}
public function test_duplicate_products_are_combined_into_one_order_item(): void
{
    Queue::fake();

    $product = Product::factory()->create([
        'name' => 'Duplicate Product',
        'price' => 100,
        'tax_percentage' => 10,
        'stock' => 10,
    ]);

    $response = $this->postJson('/api/orders', [
        'customer' => [
            'name' => 'Duplicate Test Customer',
            'email' => 'duplicate@example.com',
        ],
        'products' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
            [
                'product_id' => $product->id,
                'quantity' => 3,
            ],
        ],
    ]);

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.items.0.quantity', 5)
        ->assertJsonPath('data.subtotal', '500.00')
        ->assertJsonPath('data.tax', '50.00')
        ->assertJsonPath('data.grand_total', '550.00');

    $this->assertDatabaseCount('order_items', 1);

    $this->assertDatabaseHas('order_items', [
        'product_id' => $product->id,
        'quantity' => 5,
        'subtotal' => 500,
        'tax' => 50,
        'total' => 550,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock' => 5,
    ]);

    Queue::assertPushed(SendOrderConfirmation::class);
}
}