<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $cart = DB::transaction(function () use ($user): Cart {
            $lockedUser = $this->lockUser($user);

            return $lockedUser->cart()->firstOrCreate()->load('items.product');
        });

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $productId = (int) $validated['product_id'];

        /** @var array{cart: Cart, itemWasCreated: bool} $result */
        $result = DB::transaction(function () use ($user, $productId): array {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrCreate();
            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->firstOrFail();

            $cartItem = $cart->items()
                ->where('product_id', $product->getKey())
                ->first();

            if ($cartItem !== null) {
                return [
                    'cart' => $cart->load('items.product'),
                    'itemWasCreated' => false,
                ];
            }

            abort_if($product->stock < 1, 409, 'Product is out of stock.');

            $cart->items()->create([
                'product_id' => $product->getKey(),
                'quantity' => 1,
            ]);

            return [
                'cart' => $cart->load('items.product'),
                'itemWasCreated' => true,
            ];
        });

        return (new CartResource($result['cart']))
            ->response()
            ->setStatusCode($result['itemWasCreated'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function update(UpdateCartItemRequest $request, int $cartItemId): CartResource
    {
        $user = $this->authenticatedUser($request);
        $quantity = (int) $request->validated('quantity');

        $cart = DB::transaction(function () use ($user, $cartItemId, $quantity): Cart {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrFail();
            $cartItem = $cart->items()
                ->whereKey($cartItemId)
                ->firstOrFail();

            $product = Product::query()
                ->whereKey($cartItem->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($quantity > $product->stock, 409, 'The requested quantity exceeds available stock.');

            $cartItem->update(['quantity' => $quantity]);

            return $cart->load('items.product');
        });

        return new CartResource($cart);
    }

    public function destroy(Request $request, int $cartItemId): CartResource
    {
        $user = $this->authenticatedUser($request);

        $cart = DB::transaction(function () use ($user, $cartItemId): Cart {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrFail();
            $cartItem = $cart->items()
                ->whereKey($cartItemId)
                ->firstOrFail();

            $cartItem->delete();

            return $cart->load('items.product');
        });

        return new CartResource($cart);
    }

    public function clear(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $cart = DB::transaction(function () use ($user): Cart {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrCreate();
            $cart->items()->delete();

            return $cart->load('items.product');
        });

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function lockUser(User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
