<?php

namespace App\Services\Cart;

use App\DTOs\Cart\AddCartItemResult;
use App\Exceptions\InsufficientProductStockException;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getUserCart(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $lockedUser = $this->lockUser($user);

            return $lockedUser->cart()->firstOrCreate()->load('items.product');
        });
    }

    public function addItem(User $user, int $productId): AddCartItemResult
    {
        return DB::transaction(function () use ($user, $productId): AddCartItemResult {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrCreate();
            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
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
                    cart: $cart->load('items.product'),
                    created: false,
                );
            }

            $cart->items()->create([
                'product_id' => $product->getKey(),
                'quantity' => 1,
            ]);

            return new AddCartItemResult(
                cart: $cart->load('items.product'),
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
                ->lockForUpdate()
                ->firstOrFail();

            if ($quantity > $product->stock) {
                throw new InsufficientProductStockException;
            }

            $cartItem->update(['quantity' => $quantity]);

            return $cart->load('items.product');
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

            return $cart->load('items.product');
        });
    }

    public function clearCart(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->firstOrCreate();
            $cart->items()->delete();

            return $cart->load('items.product');
        });
    }

    private function lockUser(User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
