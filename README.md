# Laravel Store Order & Inventory Mini-System

A small Laravel-based Store Order & Inventory API developed as part of the Mallow Technologies Laravel Developer take-home assignment.

## Tech Stack

* Laravel 12
* PHP 8.2+
* MySQL
* Eloquent ORM
* PHPUnit
* Database Queue
* REST API

## Features

* Product management with stock tracking
* Customer creation and lookup by email
* Order creation with multiple products
* Duplicate product quantities are combined
* Automatic subtotal, tax, and grand total calculation
* Atomic stock deduction
* Transaction-safe concurrent stock handling
* Customer order history
* Configurable low-stock product API
* Queued order confirmation simulation
* Feature tests for successful orders and edge cases

## Requirements

* PHP 8.2+
* Composer
* MySQL
* Laravel 12 compatible environment

## Installation

Clone the repository and enter the project directory.

```bash
composer install
```

Copy the environment file:

```bash
cp .env.example .env
```

On Windows PowerShell:

```powershell
copy .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure the database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mallow_order_inventory
DB_USERNAME=root
DB_PASSWORD=
```

Configure the queue:

```env
QUEUE_CONNECTION=database
```

Run migrations and seed sample data:

```bash
php artisan migrate --seed
```

Start the Laravel development server:

```bash
php artisan serve
```

In another terminal, start the queue worker:

```bash
php artisan queue:work
```

## API Endpoints

### 1. Create Order

**POST**

```text
/api/orders
```

Example request:

```json
{
    "customer": {
        "name": "John Customer",
        "email": "john@example.com"
    },
    "products": [
        {
            "product_id": 1,
            "quantity": 2
        }
    ]
}
```

The API:

1. Creates the customer if the email does not already exist.
2. Validates product availability.
3. Calculates subtotal and tax.
4. Creates the order and order items.
5. Deducts product stock.
6. Dispatches the order confirmation job after the transaction commits.

### 2. Customer Order History

**GET**

```text
/api/customers/{email}/orders
```

Example:

```text
/api/customers/john@example.com/orders
```

Returns all orders belonging to the customer, including order items and product details.

### 3. Low Stock Products

**GET**

```text
/api/products/low-stock?threshold=50
```

The `threshold` query parameter is optional.

Default:

```text
5
```

Products with stock below the configured threshold are returned.

Example:

```text
/api/products/low-stock?threshold=10
```

## Validation

Order creation validates:

* Customer name is required
* Customer email is required and must be valid
* At least one product is required
* Product IDs must exist
* Quantity must be an integer greater than zero

The low-stock endpoint validates that the threshold is a non-negative integer.

API validation errors are returned with HTTP `422`.

## Architecture

The application follows a simple layered structure:

```text
Controller
    ↓
FormRequest validation
    ↓
OrderService
    ↓
Database Transaction
    ↓
Models / Eloquent
    ↓
Queued Job
```

### Thin Controllers

Controllers are responsible for receiving the request, invoking the appropriate service/controller logic, and returning the API response.

### FormRequest

`CreateOrderRequest` contains the order input validation rules instead of placing validation logic inside the controller.

### OrderService

`OrderService` contains the main order business logic:

* Customer lookup/creation
* Duplicate product aggregation
* Stock validation
* Price and tax calculation
* Order creation
* Order item creation
* Stock deduction
* Queue dispatch

## Concurrency and Stock Safety

Order creation is wrapped inside a database transaction.

Product rows are locked using `lockForUpdate()` before checking and deducting stock.

```php
DB::transaction(function () {
    // ...
});
```

```php
Product::whereIn('id', $productIds)
    ->lockForUpdate()
    ->get();
```

This prevents two concurrent transactions from reading and deducting the same available stock simultaneously.

For example, if a product has one unit remaining and two orders request one unit each:

```text
Request A → obtains row lock → stock = 1 → succeeds → stock = 0
Request B → waits for lock
Request B → reads stock = 0 → insufficient stock → fails
```

Therefore, stock cannot become negative and the same unit cannot be sold twice.

## Queue

A `SendOrderConfirmation` queued job is dispatched after a successful transaction:

```php
SendOrderConfirmation::dispatch($order->id)->afterCommit();
```

The current implementation simulates an email confirmation by writing the order details to the Laravel log.

The queue uses Laravel's database driver.

Run the worker with:

```bash
php artisan queue:work
```

## Testing

Run the complete test suite:

```bash
php artisan test
```

Current test coverage includes:

* Successful order creation
* Insufficient stock handling
* Customer order history
* Customer-not-found handling
* Low-stock filtering
* Duplicate product aggregation
* Stock deduction
* Order totals
* Queued confirmation dispatch

Current test result:

```text
8 tests passed
30+ assertions
```

## Design Decisions and Assumptions

### Customer Identification

Customer email is treated as the unique identifier.

If an existing email is supplied during order creation, the existing customer is reused.

The existing customer's name is not overwritten.

### Tax Calculation

Tax is calculated per order item using the product's tax percentage at the time of order creation.

### Order Item Price Snapshot

The order item stores:

* Unit price
* Tax percentage
* Subtotal
* Tax
* Total

This preserves the values used when the order was created even if the product price or tax percentage changes later.

### Duplicate Products

If the same product appears multiple times in an order request, the quantities are combined into one order item.

### Low Stock Threshold

The low-stock endpoint uses a configurable `threshold` query parameter.

Products with:

```text
stock < threshold
```

are returned.

The default threshold is `5`.

## AI Assistance

AI-assisted development was used during implementation for:

* Laravel project structure guidance
* API and validation design
* Eloquent relationship implementation
* Queue implementation guidance
* Test case design
* Concurrency and transaction reasoning
* README/documentation preparation

The actual prompts used during development are documented in the `/prompts` directory as requested in the assignment.

## License

This project was created for the Mallow Technologies Laravel Developer recruitment process.
