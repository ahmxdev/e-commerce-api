# E-Commerce API

An API-only Laravel backend for account authentication, product browsing and management, shopping carts, and order processing.

## Technology

- PHP `^8.3` and Laravel 13
- MySQL is configured by default in `.env.example`; the test suite uses in-memory SQLite.
- Laravel Sanctum issues bearer tokens. Spatie Laravel Permission provides roles and permissions.
- Brick Money is used for EGP cart and order calculations.

## Project layout

- `routes/api`: versioned routes split by authentication, products, carts, and orders.
- `app/Http/Controllers`, `Requests`, and `Resources`: HTTP handling, validation, and JSON representations.
- `app/Services/Cart` and `app/Services/Order`: transactional cart and order workflows.
- `app/Models` and `database/migrations`: Eloquent entities and schema.
- `tests/Feature`: Pest coverage for authentication, products, carts, and orders.

## Local setup

Requirements: PHP `^8.3`, Composer, and a MySQL-compatible database. The checked-in environment example uses MySQL.

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env`, then set `APP_URL`, `DB_*`, and `FRONTEND_URL` for your environment.
3. Generate the application key:

   ~~~sh
   php artisan key:generate
   ~~~

4. Run migrations and seed permission data:

   ~~~sh
   php artisan migrate --seed
   ~~~

5. Start the API locally:

   ~~~sh
   php artisan serve
   ~~~

The default mailer is `log`; verification and password-reset email content is written to `storage/logs/laravel.log`. Set a real mail transport for environments that need to deliver email. `FRONTEND_URL` is used for CORS and for links in verification and password-reset emails.

The seeders create the `super-admin` role and the product/order permissions. They do not create an administrator account or assign the role to a user; privileged account provisioning must be handled separately.

## API

All API routes are under `/api/v1`. Protected endpoints use a Sanctum bearer token. Product browsing and the authentication, verification, and password-reset entry points are public; cart and customer order routes require authentication. Administrative product and order routes also require the relevant permission.

See the [API reference](docs/api.md) for endpoint contracts, validation, response shapes, access rules, and business workflows.

## Data and business rules

A user has at most one cart. Cart items are unique per product within a cart. Orders store product-name and unit-price snapshots so later catalog edits do not change existing order details. Deleting a product is blocked while it appears in a cart; deleting one referenced only by historical orders preserves those snapshots and clears the order item's product reference.

Creating an order requires a non-empty cart, checks current stock, stores a pending cash-on-delivery order, deducts stock, and clears the cart in one transaction. Cancelled orders restore stock. Cart prices are the current product prices and do not reserve stock.

## Tests

Run the feature suite with:

~~~sh
php artisan test --compact --testsuite=Feature
~~~

PHPUnit configures tests to use an in-memory SQLite database. `phpunit.xml` defines both Feature and Unit suites.
