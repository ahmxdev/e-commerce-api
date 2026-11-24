<?php

namespace App\Http\Resources;

use App\Models\Product;
use Brick\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            'unit_price' => $this->whenLoaded('product', fn (Product $product): string => $product->price),
            'line_total' => $this->whenLoaded('product', function (Product $product): string {
                return Money::of((string) $product->price, 'EGP')->multipliedBy($this->quantity)->getAmount()->toString();
            }),
        ];
    }
}
