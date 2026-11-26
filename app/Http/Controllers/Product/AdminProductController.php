<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Services\Product\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AdminProductController extends Controller
{
    public function __construct(private readonly ProductImageService $productImageService) {}

    public function store(StoreProductRequest $request): JsonResponse
    {
        $productAttributes = Arr::except($request->validated(), ['images']);
        $imageFiles = array_values($request->file('images', []));
        $product = $this->productImageService->createProductWithImages($productAttributes, $imageFiles);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->refresh()->load('images'));
    }

    public function destroy(Product $product): JsonResponse|Response
    {
        $imagePaths = DB::transaction(function () use ($product): ?array {
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProduct->cartItems()->exists()) {
                return null;
            }

            $imagePaths = $lockedProduct->images()->pluck('path')->all();
            $lockedProduct->delete();

            return $imagePaths;
        });

        if ($imagePaths === null) {
            return response()->json([
                'message' => 'The product cannot be deleted while it is in a cart.',
            ], Response::HTTP_CONFLICT);
        }

        $this->productImageService->deleteStoredFiles($imagePaths);

        return response()->noContent();
    }
}
