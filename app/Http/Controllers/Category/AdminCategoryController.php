<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdminCategoryController extends Controller
{
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category->refresh());
    }

    public function destroy(Category $category): JsonResponse|Response
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return response()->json([
                'message' => 'The category cannot be deleted while it has products or child categories.',
            ], Response::HTTP_CONFLICT);
        }

        $category->delete();

        return response()->noContent();
    }
}
