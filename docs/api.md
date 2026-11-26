# API Reference

## Conventions

- Base path: `/api/v1`
- Send and receive JSON. Include `Accept: application/json`; include `Content-Type: application/json` when sending a JSON body. Product image upload endpoints use `multipart/form-data`.
- Protected routes require `Authorization: Bearer <token>`. Access tokens expire after 24 hours.
- Laravel JSON resources are wrapped in `data`. Authentication responses use their explicit `user`, `token`, or `message` fields. Paginated resources include `data`, `links`, and `meta`.
- Prices and totals are decimal strings in EGP. Quantities, IDs, and stock are integers.

## Example: create an order

After obtaining a token from login, send:

~~~http
POST /api/v1/orders
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json

{
  "shipping_address": "10 River Street, Cairo"
}
~~~

A successful response has HTTP `201` and a `data` resource:

~~~json
{
  "data": {
    "id": 42,
    "status": "pending",
    "payment_method": "cash_on_delivery",
    "shipping_address": "10 River Street, Cairo",
    "total_amount": "25.00",
    "items": [
      {
        "id": 73,
        "product_id": 12,
        "product_name": "Canvas Backpack",
        "unit_price": "12.50",
        "quantity": 2,
        "line_total": "25.00"
      }
    ],
    "created_at": "2026-11-25T10:30:00.000000Z"
  }
}
~~~

## Authentication and account recovery

| Method and path | Access | Request | Success and common errors |
| --- | --- | --- | --- |
| `POST /register` | Public | `name`, `email`, `password`, `password_confirmation` | `201`; validation errors `422`; rate limit `429`. Creates the account and sends an email-verification notification. |
| `POST /login` | Public | `email`, `password` | `200`; invalid credentials `401`; unverified email `403`; validation `422`; rate limit `429`. |
| `POST /logout` | Bearer token | No body | `200`; revokes the current token. |
| `GET /me` | Bearer token | — | `200` with the authenticated user. |
| `POST /email/verification-notification` | Public | `email` | `200`; validation `422`; rate limit `429`. The response is the same for unknown or already-verified addresses. |
| `POST /email/verify/{id}/{hash}` | Signed URL | Use the full temporary URL from the verification email, including its query string. | `200`; already verified `409`; invalid or expired signature `403`; missing user `404`. |
| `POST /forgot-password` | Public | `email` | `200` with a generic message; validation `422`; rate limit `429`. |
| `POST /reset-password` | Public | `email`, `token`, `password`, `password_confirmation` | `200`; invalid or expired token, or validation failure `422`. |

The user resource contains `name`, `email`, `created_at`, `updated_at`, `roles`, and `permissions`. Registration does not return an access token. A user must verify their email before login can issue one. Password-reset tokens are configured to expire after 60 minutes. The email link points to the configured frontend URL; password-reset requests intentionally return a generic success message to avoid revealing whether an address is registered.

The login limiter permits up to 5 requests per minute per email and 30 per minute per IP. Registration permits 5 per minute per IP. Verification notification, forgot-password, and reset-password requests are limited to 10 per minute per IP, 2 per minute per email, and 5 per hour per email. The password broker also enforces a 60-second reset-link cooldown. The email verification URL is limited to 10 requests per minute per IP.

## Products

Product resources contain `id`, `name`, `description`, `price`, `stock`, `images`, `created_at`, and `updated_at`. Each image contains `id`, `url`, `position`, and `is_primary`. Product lists include only the primary image; product details and administrative product responses include all images.

| Method and path | Access | Request | Success and common errors |
| --- | --- | --- | --- |
| `GET /products` | Public | Optional `page` query parameter | `200`; newest product IDs first; 15 products per page by default; primary image only. |
| `GET /products/{product}` | Public | Product ID in path | `200` with all product images; missing product `404`. |
| `POST /admin/products` | `manage-products` | `multipart/form-data`: `name`, `description`, `price`, `stock`, and `images[]` | `201`; validation `422`; unauthenticated `401`; unauthorized `403`. |
| `PATCH /admin/products/{product}` | `manage-products` | Any non-empty subset of `name`, `description`, `price`, and `stock`; images are not accepted | `200` with all product images; validation `422`; missing product `404`; unauthenticated `401`; unauthorized `403`. |
| `POST /admin/products/{product}/images` | `manage-products` | `multipart/form-data`: `images[]` | `201` with all product images; validation `422`; more than 8 total images `409`; missing product `404`; unauthenticated `401`; unauthorized `403`. |
| `DELETE /admin/products/{product}/images/{image}` | `manage-products` | Product and image IDs in path | `204`; deleting the final image `409`; missing product or image outside the product `404`; unauthenticated `401`; unauthorized `403`. |
| `PATCH /admin/products/{product}/images/reorder` | `manage-products` | `image_ids`: all image IDs for the product, once each, in the desired order | `200` with all product images; invalid image list `422`; missing product `404`; unauthenticated `401`; unauthorized `403`. |
| `DELETE /admin/products/{product}` | `manage-products` | Product ID in path | `204`; product in any cart `409`; missing product `404`; unauthenticated `401`; unauthorized `403`. |

Product creation requires a name (up to 255 characters), description, price (0 to 99,999,999.99 with at most two decimal places), and integer stock (0 to 2,147,483,647). Updates accept one or more of those fields; provided fields remain subject to the same constraints.

Product creation requires between 1 and 8 images. Uploads accept JPG, JPEG, PNG, and WebP files up to 5 MB each. Image positions are zero-based; position `0` is the primary image. Adding images appends them after the existing images, up to 8 total. Deleting an image reindexes the remaining images, and the final image cannot be deleted. To reorder images, send every image ID belonging to the product exactly once. For example: `{"image_ids": [15, 12, 18]}`. Deleting a product also removes its stored image files.

Product image URLs point to the public storage disk and use the configured `APP_URL`. Serve the local disk through `public/storage`, and configure PHP and web-server request limits for up to eight 5 MB files per request plus multipart overhead. Product creation and image uploads require `multipart/form-data`; the product update endpoint accepts product fields only and rejects `images`.

## Cart

All cart endpoints require a bearer token. A cart is created on first read or write. Cart responses are returned under `data` with `id`, `items`, and `subtotal`. Each item includes `id`, `product`, `quantity`, `unit_price`, and `line_total`; its product includes only the primary image. Cart totals use the current product price and are returned as decimal strings.

| Method and path | Request | Success and common errors |
| --- | --- | --- |
| `GET /cart` | No body | `200`; creates and returns an empty cart when needed. |
| `POST /cart/items` | `product_id` (integer, existing product) | `201` for a newly added line; `200` when the product already has a line; stock conflict `409`; validation `422`. |
| `PATCH /cart/items/{cartItemId}` | `quantity` (integer >= 1) | `200`; stock conflict on an increase beyond available stock `409`; validation `422`; item outside this user's cart `404`. |
| `DELETE /cart/items/{cartItemId}` | No body | `200` with the updated cart; item outside this user's cart `404`. |
| `DELETE /cart` | No body | `200` with an empty cart. |

Each `POST /cart/items` call adds one unit, including when that product is already in the cart. `PATCH` sets the final quantity. The implementation checks stock when the quantity is increased; decreasing the quantity does not perform a stock check. Cart operations do not reserve inventory. A product cannot be deleted while it remains in a cart.

## Orders

All customer order endpoints require a bearer token. Customer list and detail routes are scoped to the authenticated user; another user's order is reported as `404`. Order list responses are paginated (15 per page by default) and newest first. List resources omit `items`; order detail and create responses include them.

Order resources contain `id`, `status`, `payment_method`, `shipping_address`, `total_amount`, `created_at`, and `items` when loaded. Each item contains `id`, `product_id`, `product_name`, `unit_price`, `quantity`, and `line_total`. Order item names and prices are snapshots; `product_id` becomes null if the product is later deleted.

| Method and path | Access | Request | Success and common errors |
| --- | --- | --- | --- |
| `GET /orders` | Bearer token | Optional `page` query parameter | `200` with the caller's paginated orders. |
| `GET /orders/{orderId}` | Bearer token | Order ID in path | `200`; missing or another user's order `404`. |
| `POST /orders` | Bearer token | `shipping_address` (string, up to 5,000 characters) | `201`; empty cart or insufficient stock `409`; validation `422`. |
| `POST /orders/{orderId}/cancel` | Bearer token | Order ID in path; no body | `200` for a pending order; non-pending order `409`; missing or another user's order `404`. |

Order creation uses current product prices, creates a `pending` order with `cash_on_delivery` payment, deducts stock, snapshots its items, and empties the cart in one transaction. If stock is insufficient, the transaction fails and the cart remains. Customers can cancel only pending orders; cancellation restores stock.

## Administrative orders

These endpoints require authentication in addition to the listed permission. The `super-admin` role bypasses permission checks. The customer summary returned by admin order resources contains `id`, `name`, and `email`.

| Method and path | Required permission | Request | Success and common errors |
| --- | --- | --- | --- |
| `GET /admin/orders` | `view-orders` | Optional `page` query parameter | `200` with paginated orders and a `customer` summary; list items are omitted. |
| `GET /admin/orders/{orderId}` | `view-orders` | Order ID in path | `200` with customer and item details; missing order `404`. |
| `PATCH /admin/orders/{orderId}/status` | `manage-orders` | `status`: `processing`, `shipped`, `delivered`, or `cancelled` | `200`; invalid value `422`; disallowed transition `409`; missing order `404`. |

Allowed transitions are `pending` → `processing` or `cancelled`, `processing` → `shipped` or `cancelled`, and `shipped` → `delivered`. `delivered` and `cancelled` are terminal. Admin cancellation from a stock-consuming order restores its item stock.

## Error responses

Validation failures use HTTP `422` with a message and field-keyed errors:

~~~json
{
  "message": "The quantity field must be at least 1.",
  "errors": {
    "quantity": ["The quantity field must be at least 1."]
  }
}
~~~

Business conflicts, including insufficient stock, empty-cart order creation, invalid order transitions, deletion of a product present in a cart, exceeding the product image limit, and deleting the final product image, use HTTP `409` with a `message`. Unauthenticated requests use `401`; permission failures use `403`; missing resources and resources outside the caller's scope use `404`. Rate limiting uses `429`. Successful product and image deletion returns `204` with no response body.
