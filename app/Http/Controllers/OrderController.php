<?php

namespace App\Http\Controllers;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentMethodEnum;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\AdminOrderResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);

        return OrderResource::collection(
            $user->orders()->latest('id')->paginate()
        );
    }

    public function show(Request $request, int $orderId): OrderResource
    {
        $user = $this->authenticatedUser($request);
        $order = $user->orders()
            ->with('items')
            ->whereKey($orderId)
            ->firstOrFail();

        return new OrderResource($order);
    }

    public function cancel(Request $request, int $orderId): OrderResource
    {
        $user = $this->authenticatedUser($request);

        $order = DB::transaction(function () use ($user, $orderId): Order {
            $order = $user->orders()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $order->status !== OrderStatusEnum::Pending,
                Response::HTTP_CONFLICT,
                'Only pending orders can be cancelled by the customer.'
            );

            $this->transitionOrderStatus($order, OrderStatusEnum::Cancelled);

            return $order->load('items');
        });

        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId): AdminOrderResource
    {
        $nextStatus = OrderStatusEnum::from((string) $request->validated('status'));

        $order = DB::transaction(function () use ($orderId, $nextStatus): Order {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->transitionOrderStatus($order, $nextStatus);

            return $order->load(['user', 'items']);
        });

        return new AdminOrderResource($order);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $shippingAddress = (string) $request->validated('shipping_address');

        $order = DB::transaction(function () use ($user, $shippingAddress): Order {
            $lockedUser = $this->lockUser($user);
            $cart = $lockedUser->cart()->first();

            abort_if($cart === null, Response::HTTP_CONFLICT, 'Your cart is empty.');

            $cartItems = $cart->items()
                ->orderBy('product_id')
                ->get();

            abort_if($cartItems->isEmpty(), Response::HTTP_CONFLICT, 'Your cart is empty.');

            $productIds = $cartItems->pluck('product_id')->all();

            /** @var Collection<int, Product> $productsById */
            $productsById = Product::query()
                ->whereKey($productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Product $product): int => (int) $product->getKey());

            abort_if(
                $productsById->count() !== $cartItems->count(),
                Response::HTTP_CONFLICT,
                'A product in your cart is no longer available.'
            );

            $orderTotalInCents = 0;

            /** @var list<array{product_id: int|string, product_name: string, unit_price: string, quantity: int}> $orderItems */
            $orderItems = [];

            foreach ($cartItems as $cartItem) {
                $product = $productsById->get((int) $cartItem->product_id);

                abort_if(
                    $product->stock < $cartItem->quantity,
                    Response::HTTP_CONFLICT,
                    "Insufficient stock for product: {$product->name}."
                );

                $unitPriceInCents = self::amountToCents((string) $product->price);
                $orderTotalInCents += $unitPriceInCents * $cartItem->quantity;

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
                'total_amount' => self::formatCents($orderTotalInCents),
            ]);

            $order->items()->createMany($orderItems);

            foreach ($cartItems as $cartItem) {
                $productsById->get((int) $cartItem->product_id)
                    ->decrement('stock', $cartItem->quantity);
            }

            $cart->items()->delete();

            return $order->load('items');
        });

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }

    private function lockUser(User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function transitionOrderStatus(Order $order, OrderStatusEnum $nextStatus): void
    {
        abort_unless(
            $order->status->canTransitionTo($nextStatus),
            Response::HTTP_CONFLICT,
            'The requested order status transition is not allowed.'
        );

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
            ->keyBy(fn (Product $product): int => (int) $product->getKey());

        foreach ($orderItems as $orderItem) {
            $product = $productsById->get((int) $orderItem->product_id);

            if ($product instanceof Product) {
                $product->increment('stock', $orderItem->quantity);
            }
        }
    }

    private static function amountToCents(string $amount): int
    {
        [$wholeUnits, $fractionalUnits] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $wholeUnits * 100) + (int) str_pad($fractionalUnits, 2, '0');
    }

    private static function formatCents(int $amountInCents): string
    {
        return intdiv($amountInCents, 100).'.'.str_pad((string) ($amountInCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
