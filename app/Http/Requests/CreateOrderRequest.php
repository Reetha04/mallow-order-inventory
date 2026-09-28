<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer.name' => [
                'required',
                'string',
                'max:255',
            ],

            'customer.email' => [
                'required',
                'email',
            ],

            'products' => [
                'required',
                'array',
                'min:1',
            ],

            'products.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'products.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'customer.name.required' => 'Customer name is required.',
            'customer.email.required' => 'Customer email is required.',
            'customer.email.email' => 'Please provide a valid customer email.',
            'products.required' => 'At least one product is required.',
            'products.min' => 'An order must contain at least one product.',
            'products.*.product_id.exists' => 'The selected product does not exist.',
            'products.*.quantity.min' => 'Product quantity must be at least 1.',
        ];
    }
}