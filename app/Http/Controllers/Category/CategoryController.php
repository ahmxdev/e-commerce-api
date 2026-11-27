<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Category\CategoryResource;
use App\Services\Category\CategoryTreeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(CategoryTreeService $categoryTreeService): AnonymousResourceCollection
    {
        return CategoryResource::collection($categoryTreeService->tree());
    }
}
