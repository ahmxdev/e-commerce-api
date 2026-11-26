<?php

namespace App\Services\Product;

use App\Exceptions\ProductImageConflictException;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductImageService
{
    private const int MAX_IMAGES_PER_PRODUCT = 8;

    /**
     * @param  array<string, mixed>  $productAttributes
     * @param  list<UploadedFile>  $imageFiles
     */
    public function createProductWithImages(array $productAttributes, array $imageFiles): Product
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($productAttributes, $imageFiles, &$storedPaths): Product {
                $product = Product::create($productAttributes);
                $this->appendImages($product, $imageFiles, $storedPaths);

                return $product->load('images');
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFilesAfterFailure($storedPaths);

            throw $exception;
        }
    }

    /**
     * @param  list<UploadedFile>  $imageFiles
     */
    public function addImages(Product $product, array $imageFiles): Product
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($product, $imageFiles, &$storedPaths): Product {
                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->appendImages($lockedProduct, $imageFiles, $storedPaths);

                return $lockedProduct->load('images');
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFilesAfterFailure($storedPaths);

            throw $exception;
        }
    }

    public function deleteImage(Product $product, ProductImage $image): void
    {
        $imagePath = DB::transaction(function () use ($product, $image): string {
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedImage = $lockedProduct->images()
                ->whereKey($image->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProduct->images()->count() <= 1) {
                throw new ProductImageConflictException('A product must have at least one image.');
            }

            $imagePath = $lockedImage->path;
            $lockedImage->delete();
            $position = 0;

            foreach ($lockedProduct->images()->orderBy('position')->get() as $remainingImage) {
                if ($remainingImage->position !== $position) {
                    $remainingImage->position = $position;
                    $remainingImage->save();
                }

                $position++;
            }

            return $imagePath;
        });

        Storage::disk('public')->delete($imagePath);
    }

    /**
     * @param  list<int>  $imageIds
     */
    public function reorderImages(Product $product, array $imageIds): Product
    {
        return DB::transaction(function () use ($product, $imageIds): Product {
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $images = $lockedProduct->images()->get();
            $existingImageIds = $images->modelKeys();
            $requestedImageIds = array_map('intval', $imageIds);
            $sortedExistingImageIds = $existingImageIds;
            $sortedRequestedImageIds = $requestedImageIds;
            sort($sortedExistingImageIds);
            sort($sortedRequestedImageIds);

            if ($sortedExistingImageIds !== $sortedRequestedImageIds) {
                throw ValidationException::withMessages([
                    'image_ids' => ['The image_ids field must contain every image belonging to this product exactly once.'],
                ]);
            }

            $imagesById = $images->keyBy(fn (ProductImage $image): int => (int) $image->getKey());

            foreach ($images as $position => $image) {
                $image->position = self::MAX_IMAGES_PER_PRODUCT + $position;
                $image->save();
            }

            foreach ($requestedImageIds as $position => $imageId) {
                $image = $imagesById->get($imageId);
                $image->position = $position;
                $image->save();
            }

            return $lockedProduct->load('images');
        });
    }

    /**
     * @param  list<string>  $paths
     */
    public function deleteStoredFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    /**
     * @param  list<UploadedFile>  $imageFiles
     * @param  list<string>  $storedPaths
     */
    private function appendImages(Product $product, array $imageFiles, array &$storedPaths): void
    {
        $currentImageCount = $product->images()->count();

        if ($currentImageCount + count($imageFiles) > self::MAX_IMAGES_PER_PRODUCT) {
            throw new ProductImageConflictException('A product cannot have more than 8 images.');
        }

        foreach ($imageFiles as $offset => $imageFile) {
            $path = $imageFile->storePublicly('products/'.$product->getKey(), 'public');

            if (! is_string($path)) {
                throw new RuntimeException('The product image could not be stored.');
            }

            $storedPaths[] = $path;
            $product->images()->create([
                'path' => $path,
                'position' => $currentImageCount + $offset,
            ]);
        }
    }

    /**
     * @param  list<string>  $storedPaths
     */
    private function deleteStoredFilesAfterFailure(array $storedPaths): void
    {
        $this->deleteStoredFiles($storedPaths);
    }
}
