<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\Cart\CartResource;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $cart = $this->cartService->getUserCart($user);

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $productId = (int) $request->validated('product_id');

        $result = $this->cartService->addItem($user, $productId);

        return (new CartResource($result->cart))
            ->response()
            ->setStatusCode($result->created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function update(UpdateCartItemRequest $request, int $cartItemId): CartResource
    {
        $user = $this->authenticatedUser($request);
        $quantity = (int) $request->validated('quantity');

        $cart = $this->cartService->updateItemQuantity($user, $cartItemId, $quantity);

        return new CartResource($cart);
    }

    public function destroy(Request $request, int $cartItemId): CartResource
    {
        $user = $this->authenticatedUser($request);

        $cart = $this->cartService->deleteItem($user, $cartItemId);

        return new CartResource($cart);
    }

    public function clear(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $cart = $this->cartService->clearCart($user);

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
