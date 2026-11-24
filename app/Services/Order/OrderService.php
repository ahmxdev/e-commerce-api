<?php

namespace App\Services\Order;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentMethodEnum;
use App\Exceptions\InsufficientProductStockException;
use App\Exceptions\OrderConflictException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function cancelOrder(User $user, int $orderId): Order
    {
        return DB::transaction(function () use ($user, $orderId): Order {
            $order = $user->orders()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatusEnum::Pending) {
                throw new OrderConflictException('Only pending orders can be cancelled by the customer.');
            }

            $this->transitionOrderStatus($order, OrderStatusEnum::Cancelled);

            return $order->load('items');
        });
    }

    public function storeOrder(User $user, string $shippingAddress): Order
    {
        return DB::transaction(function () use ($user, $shippingAddress): Order {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->first();

            if ($cart === null) {
                throw new OrderConflictException('Your cart is empty.');
            }

            $cartItems = $cart->items()
                ->orderBy('product_id')
                ->get();

            if ($cartItems->isEmpty()) {
                throw new OrderConflictException('Your cart is empty.');
            }

            $productIds = $cartItems->pluck('product_id')->all();

            /** @var Collection<int, Product> $productsById */
            $productsById = Product::query()
                ->whereKey($productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn(Product $product): int => (int) $product->getKey());


            $orderTotal = Money::of(0, 'EGP');

            /** @var list<array{product_id: int|string, product_name: string, unit_price: string, quantity: int}> $orderItems */
            $orderItems = [];

            foreach ($cartItems as $cartItem) {
                $product = $productsById->get((int) $cartItem->product_id);

                if ($product->stock < $cartItem->quantity) {
                    throw new InsufficientProductStockException();
                }

                $unitPrice = Money::of((string) $product->price, 'EGP');
                $orderTotal = $orderTotal->plus($unitPrice->multipliedBy($cartItem->quantity));

                $orderItems[] = [
                    'product_id' => $product->getKey(),
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $cartItem->quantity,
                ];
            }

            $order = $lockedUser->orders()->create([
                'status' => OrderStatusEnum::Pending->value,
                'payment_method' => PaymentMethodEnum::CashOnDelivery->value,
                'shipping_address' => $shippingAddress,
                'total_amount' => $orderTotal->getAmount()->toString(),
            ]);

            $order->items()->createMany($orderItems);

            foreach ($cartItems as $cartItem) {
                $productsById->get((int) $cartItem->product_id)
                    ->decrement('stock', $cartItem->quantity);
            }

            $cart->items()->delete();

            return $order->load('items');
        });
    }

    public function updateOrderStatus(int $orderId, OrderStatusEnum $nextStatus): Order
    {
        return DB::transaction(function () use ($orderId, $nextStatus): Order {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->transitionOrderStatus($order, $nextStatus);

            return $order->load(['user', 'items']);
        });
    }

    private function transitionOrderStatus(Order $order, OrderStatusEnum $nextStatus): void
    {
        if (! $order->status->canTransitionTo($nextStatus)) {
            throw new OrderConflictException('The requested order status transition is not allowed.');
        }

        if ($nextStatus === OrderStatusEnum::Cancelled) {
            $this->restoreOrderStock($order);
        }

        $order->status = $nextStatus;
        $order->save();
    }
    private function restoreOrderStock(Order $order): void
    {
        $orderItems = $order->items()
            ->whereNotNull('product_id')
            ->orderBy('product_id')
            ->get();

        if ($orderItems->isEmpty()) {
            return;
        }

        $productIds = $orderItems->pluck('product_id')->unique()->all();
        $productsById = Product::query()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn(Product $product): int => (int) $product->getKey());

        foreach ($orderItems as $orderItem) {
            $product = $productsById->get((int) $orderItem->product_id);

            if ($product instanceof Product) {
                $product->increment('stock', $orderItem->quantity);
            }
        }
    }

    private function lockUser(User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
