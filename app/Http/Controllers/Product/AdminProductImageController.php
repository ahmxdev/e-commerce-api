<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ReorderProductImagesRequest;
use App\Http\Requests\Product\StoreProductImagesRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Product\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdminProductImageController extends Controller
{
    public function __construct(private readonly ProductImageService $productImageService) {}

    public function store(StoreProductImagesRequest $request, Product $product): JsonResponse
    {
        $imageFiles = array_values($request->file('images', []));
        $product = $this->productImageService->addImages($product, $imageFiles);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Product $product, ProductImage $image): Response
    {
        $this->productImageService->deleteImage($product, $image);

        return response()->noContent();
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product): ProductResource
    {
        $imageIds = array_map('intval', $request->validated('image_ids'));

        return new ProductResource($this->productImageService->reorderImages($product, $imageIds));
    }
}
