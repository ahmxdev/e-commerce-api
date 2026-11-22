# Collaboration Workflow

This document records the user's preferred way to plan and implement features in this repository. It supplements `AGENTS.md` and `CLAUDE.md`; follow their project and framework requirements as well.

## User-Led, Sequential Work

1. **Discovery comes first.** When asked to understand an area or plan a feature, inspect the current project and relevant instructions before proposing implementation. Summarize what exists, then provide a numbered, ordered task list. Do not edit code during a discovery-only request.
2. **Implement one requested task at a time.** Keep the agreed numbering stable. When the user asks for a specific point, complete that point only. Do not continue to the next point or bundle unrelated cleanup into it.
3. **Review feedback before acting.** If the user asks for an evaluation or asks why something is done, inspect the current code and explain what is correct, incorrect, or conditional. Do not treat a review question as permission to edit. When the user asks to correct or implement a suggestion, change only the relevant scope.
4. **Check the actual code and installed APIs.** Do not rely on assumptions from earlier messages when files may have changed. Read the current implementation, sibling files, relevant tests, project rules, and installed package versions. Distinguish similar behavior precisely; for example, Laravel validation omits unruled input from `validated()` but does not necessarily reject an unknown top-level field.
5. **Report progress after every task.** State what changed, where it changed, how it was checked, and which numbered tasks remain. Preserve completed work and decisions across turns.
6. **Communicate in Arabic.** The user prefers Arabic discussion. Keep code identifiers, route names, and error codes unchanged, and explain them in plain language.

Avoid unnecessary clarification when the user's intent can be inferred safely. If a choice materially changes product behavior and cannot be inferred, explain the tradeoff and ask only for that decision.

## Repository Conventions

- This is an API-only Laravel application; there is no storefront frontend in the repository.
- API routes are versioned under `/api/v1`. Follow the existing module route-file pattern and protect user-specific cart routes with `auth:sanctum`.
- Follow existing boundaries and naming: Eloquent models and relationships, Form Requests, API Resources, focused controllers, migrations, factories, and Pest feature tests. Inspect sibling files before choosing structure.
- Use Artisan generators with `--no-interaction` for Laravel files where a generator exists. Do not add dependencies or new architectural folders without authorization.
- Read the relevant project and skill rules before implementation. Confirm package versions before using version-sensitive APIs.
- Add factories for new models. Add a seeder only when there is a concrete use for seeded data; the user specifically asked not to add a cart seeder without such a reason.
- When behavior changes, add meaningful regression coverage and run the narrowest affected tests. Run `vendor/bin/pint --dirty --format agent` after changing PHP files.

## Test Organization

Keep feature test files focused on one action, following the existing product tests. For the cart, use files such as:

- `tests/Feature/Cart/ShowCartTest.php`
- `tests/Feature/Cart/StoreCartItemTest.php`
- `tests/Feature/Cart/UpdateCartItemTest.php`
- `tests/Feature/Cart/DeleteCartItemTest.php`
- `tests/Feature/Cart/ClearCartTest.php`

Do not regroup the cart lifecycle into one omnibus controller test. Keep product deletion coverage in `tests/Feature/Products/DeleteProductTest.php`. Use factories for persisted test data and assert both the HTTP result and database state for writes.

## Cart Decisions Established With the User

Treat these as the current product requirements unless the user changes them:

- Cart is for authenticated users only, with one cart per user. There is no guest-cart flow.
- Cart displays current product prices; it does not lock prices or reserve stock. Stock must be checked again during any future checkout/order flow.
- `POST /api/v1/cart/items` accepts `product_id` and adds exactly one unit. A repeated request for a product already in the cart leaves the quantity unchanged and returns the existing cart successfully (`200`). A newly added item returns `201`.
- `PATCH /api/v1/cart/items/{cartItemId}` sets the requested final quantity; it does not increment it. It must enforce the current stock limit.
- Cart operations are scoped to the authenticated user's cart. Requests for another user's item return `404`.
- Product deletion is blocked while any cart contains that product and returns `409 Conflict`; the foreign key also restricts deletion at the database level.
- Cart totals are returned as decimal strings and are calculated from integer cents. Resources expect the `items.product` relationship to be loaded.
- Current cart locks serialize operations through the user's row; product rows are locked while stock is checked. Per-item locks were removed as redundant under that shared cart-operation lock. Any future code path that mutates the cart must follow the same locking protocol.
- Checkout, orders, and payment are separate future modules, outside the current cart scope.

## Verification Note

On 2026-11-22, all existing feature tests passed (90 tests, 304 assertions), and the cart routes were confirmed to use `auth:sanctum`. The full `php artisan test --compact` command stopped before running tests because `phpunit.xml` references a missing `tests/Unit` directory. Recheck this repository state before relying on it; report the full-suite limitation accurately and run the existing feature suite separately when needed.
