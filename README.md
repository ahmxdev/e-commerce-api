# E-Commerce API (MVP)

A Laravel REST API for authentication, products, shopping carts, and orders.

## Requirements

- PHP 8.3 or later
- Composer
- MySQL

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set your database credentials and `FRONTEND_URL` in `.env`. Then run:

```bash
php artisan migrate --seed
php artisan serve
```

Mail uses the `log` driver by default. Messages are written to `storage/logs/laravel.log`. Seeding creates roles and permissions, but does not create an admin user.

## API

All routes use the `/api/v1` prefix and return JSON. Protected routes require a Sanctum bearer token:

```http
Authorization: Bearer <token>
```

- **Authentication:** registration, login, profile, logout, email verification, and password reset.
- **Products:** public listing and details; create, update, and delete require permission.
- **Cart:** available to authenticated users.
- **Orders:** users manage their own orders; admin order management requires permission.

## Tests

```bash
php artisan test --compact --testsuite=Feature
```
