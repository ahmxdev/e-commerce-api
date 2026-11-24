<?php

namespace App\Http\Controllers;

use App\Enums\Order\OrderStatusEnum;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\Order\OrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    function __construct(
        private readonly OrderService $orderService
    ) {}
    public function index(): AnonymousResourceCollection
    {
        return AdminOrderResource::collection(
            Order::query()
                ->with('user')
                ->latest('id')
                ->paginate()
        );
    }

    public function show(int $orderId): AdminOrderResource
    {
        $order = Order::query()
            ->with(['user', 'items'])
            ->whereKey($orderId)
            ->firstOrFail();

        return new AdminOrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId): AdminOrderResource
    {
        $nextStatus = OrderStatusEnum::from((string) $request->validated('status'));

        $order = $this->orderService->updateOrderStatus($orderId, $nextStatus);

        return new AdminOrderResource($order);
    }
}
