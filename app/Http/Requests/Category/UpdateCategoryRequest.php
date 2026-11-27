<?php

namespace App\Http\Requests\Category;

use App\Enums\Auth\PermissionEnum;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::ManageCategories->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
        ];
    }

    /**
     * Get the after validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['name', 'parent_id'])) {
                    $validator->errors()->add('category', 'At least one category field must be provided.');
                }

                if ($validator->errors()->has('parent_id') || ! $this->has('parent_id') || $this->input('parent_id') === null) {
                    return;
                }

                $category = $this->route('category');

                if (! $category instanceof Category) {
                    return;
                }

                $parentIdsByCategory = Category::query()->pluck('parent_id', 'id');
                $ancestorId = (int) $this->input('parent_id');
                $visitedCategoryIds = [];

                while ($ancestorId !== 0 && ! isset($visitedCategoryIds[$ancestorId])) {
                    if ($ancestorId === (int) $category->getKey()) {
                        $validator->errors()->add(
                            'parent_id',
                            'The parent category cannot be the category itself or one of its descendants.'
                        );

                        return;
                    }

                    $visitedCategoryIds[$ancestorId] = true;
                    $nextAncestorId = $parentIdsByCategory->get($ancestorId);
                    $ancestorId = $nextAncestorId === null ? 0 : (int) $nextAncestorId;
                }
            },
        ];
    }
}
