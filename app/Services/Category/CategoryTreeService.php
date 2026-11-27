<?php

namespace App\Services\Category;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryTreeService
{
    /**
     * @return Collection<int, Category>
     */
    public function tree(): Collection
    {
        $categories = Category::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $categoriesById = $categories->keyBy(fn(Category $category): int => (int) $category->getKey());
        $rootCategories = collect();

        foreach ($categories as $category) {
            $category->setRelation('children', collect());
        }

        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                $rootCategories->push($category);

                continue;
            }

            $parentCategory = $categoriesById->get((int) $category->parent_id);

            if ($parentCategory instanceof Category) {
                $parentCategory->children->push($category);
            }
        }

        return $rootCategories;
    }

    /**
     * @return list<int>
     */
    public function idsInSubtree(int $categoryId): array
    {
        $parentIdsByCategoryId = Category::query()->pluck('parent_id', 'id');

        /** @var array<int, list<int>> $childIdsByParentId */
        $childIdsByParentId = [];

        foreach ($parentIdsByCategoryId as $id => $parentId) {
            if ($parentId !== null) {
                $childIdsByParentId[(int) $parentId][] = (int) $id;
            }
        }

        $categoryIds = [$categoryId];
        $visitedCategoryIds = [$categoryId => true];

        for ($index = 0; $index < count($categoryIds); $index++) {
            foreach ($childIdsByParentId[$categoryIds[$index]] ?? [] as $childId) {
                if (isset($visitedCategoryIds[$childId])) {
                    continue;
                }

                $visitedCategoryIds[$childId] = true;
                $categoryIds[] = $childId;
            }
        }

        return $categoryIds;
    }
}
