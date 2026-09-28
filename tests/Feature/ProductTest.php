<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_products_can_be_filtered_by_threshold(): void
    {
        Product::factory()->create([
            'name' => 'Low Stock Product',
            'stock' => 3,
        ]);

        Product::factory()->create([
            'name' => 'Normal Stock Product',
            'stock' => 20,
        ]);

        $response = $this->getJson('/api/products/low-stock?threshold=5');

        $response
            ->assertStatus(200)
            ->assertJsonPath('threshold', '5')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Low Stock Product')
            ->assertJsonPath('data.0.stock', 3);
    }
}