<?php

namespace App\Services\Cart;

use App\DTOs\Cart\AddCartItemResult;
use App\Exceptions\InsufficientProductStockException;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getUserCart(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $lockedUser = $this->lockUser($user);

            return $this->loadCartWithPrimaryProductImages($lockedUser->cart()->firstOrCreate());
        });
    }

    public function addItem(User $user, int $productId): AddCartItemResult
    {
        return DB::transaction(function () use ($user, $productId): AddCartItemResult {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrCreate();
            $product = Product::query()
                ->whereKey($productId)
                ->firstOrFail();

            if ($product->stock < 1) {
                throw new InsufficientProductStockException;
            }

            $cartItem = $cart->items()
                ->where('product_id', $product->getKey())
                ->first();

            if ($cartItem) {
                if ($product->stock < $cartItem->quantity + 1) {
                    throw new InsufficientProductStockException;
                }

                $cartItem->update(['quantity' => $cartItem->quantity + 1]);

                return new AddCartItemResult(
                    cart: $this->loadCartWithPrimaryProductImages($cart),
                    created: false,
                );
            }

            $cart->items()->create([
                'product_id' => $product->getKey(),
                'quantity' => 1,
            ]);

            return new AddCartItemResult(
                cart: $this->loadCartWithPrimaryProductImages($cart),
                created: true,
            );
        });
    }

    public function updateItemQuantity(User $user, int $cartItemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $cartItemId, $quantity): Cart {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrFail();
            $cartItem = $cart->items()
                ->whereKey($cartItemId)
                ->firstOrFail();

            $product = Product::query()
                ->whereKey($cartItem->product_id)
                ->firstOrFail();

            // Only validate stock when increasing the item quantity.
            if ($quantity > $cartItem->quantity && $quantity > $product->stock) {
                throw new InsufficientProductStockException;
            }

            $cartItem->update(['quantity' => $quantity]);

            return $this->loadCartWithPrimaryProductImages($cart);
        });
    }

    public function deleteItem(User $user, int $cartItemId): Cart
    {
        return DB::transaction(function () use ($user, $cartItemId): Cart {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrFail();
            $cartItem = $cart->items()
                ->whereKey($cartItemId)
                ->firstOrFail();

            $cartItem->delete();

            return $this->loadCartWithPrimaryProductImages($cart);
        });
    }

    public function clearCart(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrCreate();
            $cart->items()->delete();

            return $this->loadCartWithPrimaryProductImages($cart);
        });
    }

    private function lockUser(User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function loadCartWithPrimaryProductImages(Cart $cart): Cart
    {
        return $cart->load([
            'items.product.images' => function (HasMany $images): void {
                $images->where('position', 0);
            },
        ]);
    }
}
