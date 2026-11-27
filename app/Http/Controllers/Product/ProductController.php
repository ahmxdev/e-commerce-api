<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductsRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Services\Category\CategoryTreeService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(
        IndexProductsRequest $request,
        CategoryTreeService $categoryTreeService
    ): AnonymousResourceCollection {
        $productQuery = Product::query()
            ->with([
                'category',
                'images' => function (HasMany $images): void {
                    $images->where('position', 0);
                },
            ]);
        $categoryId = $request->validated('category_id');

        if ($categoryId !== null) {
            $productQuery->whereIn(
                'category_id',
                $categoryTreeService->idsInSubtree((int) $categoryId)
            );
        }

        return ProductResource::collection($productQuery->latest('id')->paginate());
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'images']));
    }
}
