<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminOrderController extends Controller
{
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
}
