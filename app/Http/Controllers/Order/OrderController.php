<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\Order\OrderResource;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrderController extends Controller
{

    function __construct(
        private readonly OrderService $orderService
    ) {}

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

        $order = $this->orderService->cancelOrder($user, (string) $orderId);

        return new OrderResource($order);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $shippingAddress = (string) $request->validated('shipping_address');

        $order = $this->orderService->storeOrder($user, $shippingAddress);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
